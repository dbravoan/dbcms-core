<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Middleware;

use Closure;
use DbCms\Core\Services\Cms\CacheDependencyCollector;
use DbCms\Core\Services\Cms\PageCacheService;
use DbCms\Core\Services\Cms\PublicPagePersonalization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caches full public HTML responses (short TTL + ETag) and serves them without
 * hitting the database again.
 *
 * Safety rules — a response is NEVER cached/served when:
 *   - the request is not a cacheable GET (POST/PUT/…, AJAX, Range, non-HTML),
 *   - a session is authenticated (backoffice or frontoffice bar),
 *   - preview/editor query flags are present,
 *   - the response is not a plain 200 OK,
 *   - the route is admin/api/install/forms/maintenance.
 *
 * Content freshness is event-driven: model observers call
 * PageCacheService::invalidate(), which bumps the namespace version.
 */
final class CachePublicPage
{
    /**
     * Query flags that force a live render (front-office editors / previews).
     *
     * @var array<int, string>
     */
    private const BYPASS_QUERY = ['preview', 'edit', 'no_cache', 'nocache'];

    /**
     * Response header marking a cache hit/miss (observability + tests).
     */
    public const HIT_HEADER = 'X-Page-Cache';

    public function __construct(
        private readonly PageCacheService $cache,
        private readonly CacheDependencyCollector $collector,
        private readonly PublicPagePersonalization $personalization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldConsider($request)) {
            $response = $next($request);
            $this->applyCacheHeaders($response);

            return $response;
        }

        $key = $this->cache->key($request->getHost(), app()->getLocale(), $request->getRequestUri().':'.(app()->bound('current.market') ? app('current.market')->code : ''));
        $cached = $this->read($key);

        if ($cached !== null) {
            try {
                return $this->fromCache($cached);
            } catch (\Throwable) {
                Cache::forget($key);
            }
        }

        $this->collector->start();
        foreach (['pages', 'posts', 'products', 'galleries'] as $type) {
            $this->collector->recordType($type);
        }

        try {
            $response = $next($request);
        } finally {
            $dependencies = $this->collector->stop();
        }

        if ($this->shouldStore($request, $response)) {
            $this->write($key, $response, $dependencies);
        }
        $this->applyCacheHeaders($response);

        return $response;
    }

    /**
     * Whether the request is a candidate for the public HTML cache.
     */
    private function shouldConsider(Request $request): bool
    {
        if (! $this->cache->enabled()) {
            return false;
        }
        if (($request->route()?->defaults['_cms_cache_public'] ?? null) === false) {
            return false;
        }

        if (! $request->isMethodCacheable() || $request->ajax() || $request->isJson()) {
            return false;
        }

        // Authenticated visitors get a personalised bar → never cache.
        if ($request->hasSession() && $request->session()->isStarted() && auth()->check()) {
            return false;
        }
        if ($request->hasSession() && ($request->session()->has('_old_input') || $request->session()->has('errors') || $request->session()->has('success') || $request->session()->has('form_success') || $request->session()->has('error'))) {
            return false;
        }
        $action = (string) ($request->route()?->getAction('controller') ?? '');
        if (preg_match('/@(cart|checkout|orderSuccess)$/', $action) === 1) {
            return false;
        }

        foreach (self::BYPASS_QUERY as $flag) {
            if ($request->query->has($flag)) {
                return false;
            }
        }

        $path = trim($request->path(), '/');

        foreach (['admin', 'api', 'install', 'up', 'forms', 'geo'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the produced response is safe and useful to cache.
     */
    private function shouldStore(Request $request, Response $response): bool
    {
        if ($request->attributes->get('_cms_cache_public') === false) {
            return false;
        }
        if (! $response->isSuccessful()) {
            return false;
        }

        if ($response->headers->has('Set-Cookie')) {
            return false;
        }

        // The frontoffice admin bar / section editor requires auth → never cache.
        if (auth()->check()) {
            return false;
        }

        // Never cache responses from front-office editing endpoints.
        $path = trim($request->path(), '/');
        if (str_contains($path, 'preview') || str_contains($path, 'edit')) {
            return false;
        }

        // Challenges carry encrypted, expiring tokens. Render protected forms live.
        $html = (string) $response->getContent();
        if (str_contains($html, 'srcdoc=') || str_contains($html, '_captcha_token') || str_contains($html, 'g-recaptcha') || str_contains($html, 'cf-turnstile')) {
            return false;
        }

        // Only cache HTML documents.
        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }

    /**
     * @return array{content: string, etag: string, nonce?: string|null}|null
     */
    private function read(string $key): ?array
    {
        try {
            $payload = Cache::get($key);
        } catch (\Throwable) {
            return null;
        }

        return is_array($payload) && isset($payload['content'], $payload['etag']) ? $payload : null;
    }

    /**
     * @param  array<int, string>  $dependencyKeys
     */
    private function write(string $key, Response $response, array $dependencyKeys = []): void
    {
        $content = $this->personalization->snapshot((string) $response->getContent(), (string) csrf_token());

        if ($content === '') {
            return;
        }

        $etag = '"'.sha1($content).'"';

        try {
            Cache::put($key, [
                'content' => $content,
                'etag' => $etag,
                // The rendered HTML embeds the current CSP nonce; store it so it
                // can be rotated on a cache hit (a stale nonce would make the
                // browser block every script/style on the cached page).
                'nonce' => Vite::cspNonce(),
            ], $this->cache->ttl());

            $this->cache->registerDependencies($key, $dependencyKeys);
        } catch (\Throwable) {
            return;
        }

        $response->headers->set('ETag', '"'.sha1((string) $response->getContent()).'"');
        $response->headers->set(self::HIT_HEADER, 'MISS');
        $this->applyCacheHeaders($response);
    }

    /**
     * Serves a cached HTML document without touching the database.
     *
     * @param  array{content: string, etag: string, nonce?: string|null}  $cached
     */
    private function fromCache(array $cached): Response
    {
        $content = $this->personalization->hydrate((string) $cached['content'], (string) csrf_token(), static fn ($view): string => view($view)->render());
        $content = $this->withFreshNonce($content, $cached['nonce'] ?? null);

        $response = response($content, 200);
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->headers->set('ETag', '"'.sha1($content).'"');
        $response->headers->set(self::HIT_HEADER, 'HIT');
        $this->applyCacheHeaders($response);

        return $response;
    }

    /**
     * Rotate the CSP nonce baked into a cached document to the current request's
     * nonce, so scripts/styles are not blocked by the Content-Security-Policy.
     */
    private function withFreshNonce(string $content, ?string $storedNonce): string
    {
        $currentNonce = Vite::cspNonce();

        if ($storedNonce === null || $storedNonce === '' || $currentNonce === null || $storedNonce === $currentNonce) {
            return $content;
        }

        return str_replace('nonce="'.$storedNonce.'"', 'nonce="'.$currentNonce.'"', $content);
    }

    private function applyCacheHeaders(Response $response): void
    {
        // Neutral snapshots are shared internally; hydrated HTML is private.
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    }
}
