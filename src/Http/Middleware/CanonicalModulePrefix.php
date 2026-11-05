<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consolidates non-canonical module prefixes with a 301.
 *
 * Module slug prefixes are localizable (`/tienda` in Spanish, `/shop` in
 * English), so every non-active alias of the same module — `/shop` while
 * browsing in Spanish — would serve the very same content on a second URL.
 * This middleware answers such requests with a single permanent redirect to
 * the canonical prefix, preserving the rest of the path and the query string.
 *
 * It runs after {@see ResolveMarketAndLocaleMiddleware} (so the active locale
 * and the route parameters are known) and before the public page cache, so the
 * redirect itself is never cached as content.
 */
final class CanonicalModulePrefix
{
    public function __construct(
        private readonly LocalizationServiceInterface $localization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $hasMarket = (string) ($request->route('market') ?? '') !== '';
        $routeLocale = (string) ($request->route('locale') ?? '');
        $locale = (string) App::getLocale();

        // An unknown locale is not our business: leave the request untouched.
        if ($routeLocale !== '' && strtolower($routeLocale) !== strtolower($locale)) {
            return $next($request);
        }

        // The prefix is the first segment after the (optional) market + locale.
        $index = ($hasMarket ? 1 : 0) + ($routeLocale !== '' ? 1 : 0);
        $requested = $request->segment($index + 1);

        if ($requested === null || $requested === '') {
            return $next($request);
        }

        $moduleKey = $this->localization->moduleAliases()[strtolower($requested)] ?? null;

        if ($moduleKey === null) {
            return $next($request);
        }

        $canonical = $this->localization->getModulePrefix($moduleKey, $locale);

        if ($canonical === $requested) {
            return $next($request);
        }

        // Rebuild from the raw path so percent-encoded slugs survive untouched.
        $parts = explode('/', trim($request->getPathInfo(), '/'));
        $parts[$index] = rawurlencode($canonical);
        $url = '/'.implode('/', $parts);

        $query = $request->getQueryString();

        if ($query !== null && $query !== '') {
            $url .= '?'.$query;
        }

        return redirect()->to($url, 301);
    }
}
