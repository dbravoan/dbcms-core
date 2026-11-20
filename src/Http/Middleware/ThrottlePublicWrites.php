<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * IP-based rate limiting for public (unauthenticated) write endpoints.
 *
 * Covers front-office POSTs such as form submissions, blog comments, shop
 * checkout/inquiry/cart and the geo preference cookie, which otherwise have no
 * throttling. Back-office, installer, OAuth2 and MCP endpoints are excluded:
 * they are already authenticated and/or carry their own protection.
 *
 * The threshold lives in `cms.public_writes_per_minute` and the limiter is
 * registered as `public-writes` in AppServiceProvider.
 */
final class ThrottlePublicWrites
{
    /**
     * Path prefixes that must never be throttled by this middleware.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_PREFIXES = [
        'install',
        'oauth',
        'up',
        'mcp',
        'theme-assets',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldThrottle($request)) {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, 'public-writes');
    }

    private function shouldThrottle(Request $request): bool
    {
        if (! $request->isMethod('POST')) {
            return false;
        }

        $adminPath = trim((string) config('cms.admin_path', 'admin'), '/');

        $excluded = array_values(array_filter(
            array_merge([$adminPath], self::EXCLUDED_PREFIXES),
            static fn (string $prefix): bool => $prefix !== '',
        ));

        return ! $request->is(...array_map(
            static fn (string $prefix): string => $prefix.'*',
            $excluded,
        ));
    }
}
