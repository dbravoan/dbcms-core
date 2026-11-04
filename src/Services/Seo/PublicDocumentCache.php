<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use Closure;
use DbCms\Core\Services\Cms\PageCacheService;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Public document cache shares publication expiry and invalidation with pages. */
final class PublicDocumentCache
{
    public function __construct(private readonly PageCacheService $pages) {}

    /** @param Closure(): string $generate */
    public function remember(string $key, Closure $generate): string
    {
        $cacheKey = sprintf('cms:document:%s:%s', $this->pages->visibilityVersion(), $key);
        try {
            $value = Cache::get($cacheKey);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        } catch (Throwable) {
            // A cache outage must not hide public content.
        }
        // Generator exceptions propagate; never repeat failed domain work.
        $value = $generate();
        try {
            Cache::put($cacheKey, $value, $this->pages->ttl());
        } catch (Throwable) {
            // The generated document remains usable without cache storage.
        }

        return $value;
    }
}
