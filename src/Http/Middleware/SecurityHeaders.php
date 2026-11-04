<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Security\Contracts\ContentSecurityPolicyContributorInterface;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies baseline security headers to every response.
 *
 * Themes use self-hosted assets by default. Configured modules contribute exact
 * network origins through a core contract. Inline script/style blocks carry a
 * per-request nonce shared with the Blade views.
 */
final class SecurityHeaders
{
    /** @param iterable<ContentSecurityPolicyContributorInterface> $contributors */
    public function __construct(#[Tag('cms.csp.connect')] private readonly iterable $contributors = []) {}

    /**
     * Request attribute name carrying the CSP nonce for this response.
     */
    public const NONCE_ATTRIBUTE = 'csp-nonce';

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $request->attributes->set(self::NONCE_ATTRIBUTE, $nonce);

        // Share with Blade so inline <script> blocks can carry the nonce.
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
        ];

        // Only meaningful (and only safe) over HTTPS.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // Never override a header set intentionally downstream.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Self-hosted CSP extended by configured module contributors.
     */
    private function contentSecurityPolicy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            // Alpine.js evaluates x-data/x-on expressions at runtime, which
            // requires 'unsafe-eval' with the standard build.
            "script-src 'self' 'unsafe-eval' 'nonce-{$nonce}' 'strict-dynamic'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        $assetOrigins = app(ExternalContentPolicy::class)->assetOrigins();
        if ($assetOrigins !== []) {
            $directives = array_map(
                static fn (string $directive): string => str_starts_with($directive, 'img-src') || str_starts_with($directive, 'media-src')
                    ? $directive.' '.implode(' ', $assetOrigins) : $directive,
                $directives
            );
        }

        $connectOrigins = [];
        $frameOrigins = [];
        foreach ($this->contributors as $contributor) {
            $connectOrigins = array_merge($connectOrigins, $contributor->connectOrigins());
            $frameOrigins = array_merge($frameOrigins, $contributor->frameOrigins());
        }
        if ($connectOrigins !== []) {
            $directives = array_map(
                static fn (string $directive): string => str_starts_with($directive, 'connect-src')
                    ? $directive.' '.implode(' ', array_unique($connectOrigins)) : $directive,
                $directives
            );
        }

        if ($frameOrigins !== []) {
            $directives[] = "frame-src 'self' ".implode(' ', array_unique($frameOrigins));
        }

        // Local development talks to the Vite dev server. Only reachable
        // directives are extended: value-less directives such as `object-src
        // 'none'` or `frame-ancestors` must keep their single keyword.
        if (app()->environment('local')) {
            $extendable = [
                'default-src',
                'script-src',
                'style-src',
                'img-src',
                'font-src',
                'connect-src',
                'media-src',
            ];

            $directives = array_map(
                static function (string $directive) use ($extendable): string {
                    $name = strtok($directive, ' ');

                    return in_array($name, $extendable, true)
                        ? $directive.' http://localhost:5173 ws://localhost:5173'
                        : $directive;
                },
                $directives
            );
        }

        return implode('; ', $directives);
    }
}
