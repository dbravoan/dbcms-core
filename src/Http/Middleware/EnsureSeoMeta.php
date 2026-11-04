<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Services\Cms\ThemeManagerService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a request as a public HTML page so the global SEO composer (registered
 * once in SeoServiceProvider) knows it should fill in metadata.
 *
 * The composer itself is NOT registered here: registering it per request would
 * accumulate callbacks in long-lived workers and duplicate work.
 */
final class EnsureSeoMeta
{
    /**
     * Request attribute set on public HTML requests.
     */
    public const PUBLIC_REQUEST_ATTRIBUTE = 'seo.public_request';

    public function handle(Request $request, Closure $next): Response
    {
        // Views resolve per request; drop memoised resolution so a theme switch in a
        // long-lived process cannot leak the previous theme's files.
        if (class_exists(ThemeManagerService::class)) {
            app(ThemeManagerService::class)->syncActiveTheme();
        }

        $request->attributes->set(self::PUBLIC_REQUEST_ATTRIBUTE, $this->applies($request));

        return $next($request);
    }

    /**
     * Whether this is a public, cacheable HTML page request.
     */
    private function applies(Request $request): bool
    {
        if (! $request->isMethodCacheable() || $request->ajax() || $request->isJson()) {
            return false;
        }

        $path = trim($request->path(), '/');

        foreach (['admin', 'api', 'install', 'up', 'media'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return true;
    }
}
