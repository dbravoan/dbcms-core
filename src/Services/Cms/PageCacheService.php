<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use Illuminate\Support\Facades\Cache;

/**
 * Central switch + key registry for public page caching.
 *
 * Content changes constantly, so instead of long TTLs we use short TTLs plus an
 * explicit namespace version that model observers bump on save/delete. Bumping
 * the version instantly invalidates every cached page without flushing the store.
 */
final class PageCacheService
{
    public const VERSION_KEY = 'cms:page-cache:version';

    public const INDEX_VERSION_KEY = 'cms:public-index:version';

    public function indexVersion(): int
    {
        try {
            return (int) Cache::get(self::INDEX_VERSION_KEY, 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    public function visibilityVersion(): string
    {
        return $this->indexVersion().':'.app(PublicationBoundaryService::class)->state()['fingerprint'];
    }

    public function invalidateIndexes(): void
    {
        try {
            Cache::add(self::INDEX_VERSION_KEY, 1);
            Cache::increment(self::INDEX_VERSION_KEY);
        } catch (\Throwable) {
            // Cache failure must not interrupt content edits.
        }
    }

    /**
     * Prefix for the reverse index: dependency key → cached page keys.
     */
    public const DEP_PREFIX = 'cms:page-dep:';

    /**
     * Whether public HTML caching is enabled for this environment.
     */
    public function enabled(): bool
    {
        return (bool) config('cms.page_cache.enabled', true);
    }

    /**
     * Cache TTL in seconds (short by design: invalidation is event-driven).
     */
    public function ttl(): int
    {
        $configured = max(1, (int) config('cms.page_cache.ttl', 300));
        $boundary = app(PublicationBoundaryService::class)->state()['nextSeconds'];

        return $boundary === null ? $configured : min($configured, $boundary);
    }

    /**
     * Current cache namespace version.
     */
    public function version(): int
    {
        try {
            return (int) Cache::get(self::VERSION_KEY, 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    /**
     * Invalidates every cached page by bumping the namespace version.
     *
     * Cheap and safe: no key scanning, and old entries expire on their own TTL.
     */
    public function invalidate(): void
    {
        $this->invalidateIndexes();
        try {
            Cache::forever(self::VERSION_KEY, $this->version() + 1);
        } catch (\Throwable) {
            // Cache store unavailable: pages will simply not be cached.
        }
    }

    /**
     * Builds the cache key for a request fingerprint.
     *
     * The market/locale are part of the URI (`/{market}/{locale}/…`), so the URI
     * alone is a sufficient fingerprint and avoids depending on middleware order.
     */
    public function key(string $market, string $locale, string $uri): string
    {
        return sprintf('cms:page:v3:%d:%s:%s', $this->version(), app(PublicationBoundaryService::class)->state()['fingerprint'], sha1($market.':'.$locale.':'.$uri));
    }

    /**
     * Dependency key for a specific entity (plain form: `{type}:{id}`).
     */
    public function dependencyKey(string $type, int|string $id): string
    {
        return $type.':'.$id;
    }

    /**
     * Dependency key for "any entity of this type" (plain form: `{type}:*`).
     */
    public function typeDependencyKey(string $type): string
    {
        return $type.':*';
    }

    /**
     * Cache key holding the reverse index for a dependency.
     */
    private function indexKey(string $dependency): string
    {
        return self::DEP_PREFIX.$dependency;
    }

    /**
     * Register a cached page under each of its dependency keys (reverse index).
     *
     * @param  array<int, string>  $dependencyKeys  Plain dependency keys (e.g. `posts:12`).
     */
    public function registerDependencies(string $pageKey, array $dependencyKeys): void
    {
        $ttl = $this->ttl() + 60;

        foreach ($dependencyKeys as $dependency) {
            try {
                $index = Cache::get($this->indexKey($dependency), []);
                $index = is_array($index) ? $index : [];
                $index[$pageKey] = true;

                Cache::put($this->indexKey($dependency), $index, $ttl);
            } catch (\Throwable) {
                // Cache store unavailable: pages will expire on their own TTL.
            }
        }
    }

    /**
     * Forget every page registered under the given dependency keys.
     *
     * @param  array<int, string>  $dependencyKeys  Plain dependency keys (e.g. `posts:12`).
     */
    public function invalidateDependencies(array $dependencyKeys): void
    {
        foreach ($dependencyKeys as $dependency) {
            try {
                $index = Cache::get($this->indexKey($dependency));

                if (is_array($index)) {
                    foreach (array_keys($index) as $pageKey) {
                        Cache::forget($pageKey);
                    }
                }

                Cache::forget($this->indexKey($dependency));
            } catch (\Throwable) {
                // Never leave stale pages behind if the store misbehaves.
                $this->invalidate();

                return;
            }
        }
    }

    /**
     * Invalidate every page that depended on any instance of $type.
     *
     * Used on create/delete, where a specific id was not part of any cached page.
     */
    public function invalidateType(string $type): void
    {
        $this->invalidateIndexes();
        $this->invalidateDependencies([$this->typeDependencyKey($type)]);
    }
}
