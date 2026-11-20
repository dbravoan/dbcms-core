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

    private const RENDER_REVISION_KEY = 'cms:page-cache:render-revision';

    private const DEPENDENCY_PREFIX = 'cms:page-dependency-version:';

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
        $version = $this->indexVersion();

        return $version.':'.app(PublicationBoundaryService::class)->state($version)['fingerprint'];
    }

    public function invalidateIndexes(): void
    {
        try {
            Cache::forever(self::INDEX_VERSION_KEY, random_int(2, PHP_INT_MAX));
        } catch (\Throwable) {
            // Cache failure must not interrupt content edits.
        }
    }

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
            Cache::lock(self::VERSION_KEY.':lock', 10)->block(1, function (): void {
                Cache::forever(self::VERSION_KEY, $this->version() + 1);
            });
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
        return sprintf('cms:page:v4:%d:%s:%s', $this->version(), app(PublicationBoundaryService::class)->state()['fingerprint'], sha1($market.':'.$locale.':'.$uri));
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

    public function renderRevision(): ?string
    {
        try {
            return (string) Cache::get(self::RENDER_REVISION_KEY, '');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $dependencies
     * @return array<string,string>
     */
    public function dependencyVersions(array $dependencies): array
    {
        $keys = array_map(static fn (string $dependency): string => self::DEPENDENCY_PREFIX.$dependency, $dependencies);
        $versions = [];
        foreach (Cache::many($keys) as $key => $version) {
            $versions[substr($key, strlen(self::DEPENDENCY_PREFIX))] = is_string($version) ? $version : '';
        }

        ksort($versions);

        return $versions;
    }

    /** @param list<string> $dependencyKeys */
    public function invalidateDependencies(array $dependencyKeys): void
    {
        if ($dependencyKeys === []) {
            return;
        }

        try {
            $version = bin2hex(random_bytes(16));
            $ttl = max(86400, (int) config('cms.page_cache.ttl', 300)) + 60;
            // Change the revision first so in-flight misses cannot publish an old render.
            if (! Cache::forever(self::RENDER_REVISION_KEY, $version)) {
                $this->invalidate();

                return;
            }
            foreach (array_unique($dependencyKeys) as $dependency) {
                if (! Cache::put(self::DEPENDENCY_PREFIX.$dependency, $version, $ttl)) {
                    $this->invalidate();

                    return;
                }
            }
        } catch (\Throwable) {
            $this->invalidate();
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
