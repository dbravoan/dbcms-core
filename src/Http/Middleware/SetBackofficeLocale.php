<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Services\Cms\BackofficeLocaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the backoffice UI language from the user's preference.
 *
 * Runs after the public market/locale middleware and intentionally overrides
 * the application locale so the admin panel is never bound to the visitor's
 * market context.
 */
class SetBackofficeLocale
{
    public function __construct(
        protected BackofficeLocaleService $locales
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->locales->resolve($request));

        return $next($request);
    }
}
