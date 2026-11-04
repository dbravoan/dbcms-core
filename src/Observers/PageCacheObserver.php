<?php

declare(strict_types=1);

namespace DbCms\Core\Observers;

use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Services\Cms\PageCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Invalidates the public page cache whenever content changes.
 *
 * Invalidation is dependency-based: updating an entity only drops the pages
 * that actually read it, while creating/deleting drops every page that read any
 * instance of its type (a brand-new id is not part of any cached page).
 */
final class PageCacheObserver
{
    public function __construct(
        private readonly PageCacheService $cache
    ) {}

    public function saved(Model $model): void
    {
        $this->invalidateSectionOwner($model);
        if (DB::transactionLevel() > 0) {
            $global = $this->isSiteWide($model);
            $type = $model->getTable();
            $id = (string) $model->getKey();
            $membership = $model->wasRecentlyCreated || $model->wasChanged(['is_published', 'is_active', 'published_at', 'category_id', 'order', 'is_featured', 'price']);
            DB::afterCommit(function () use ($global, $type, $id, $membership): void {
                if ($global) {
                    $this->cache->invalidate();
                } elseif ($membership) {
                    $this->cache->invalidateType($type);
                } else {
                    $this->cache->invalidateDependencies([$this->cache->dependencyKey($type, $id)]);
                    $this->cache->invalidateIndexes();
                }
            });
        }
        if ($this->isSiteWide($model)) {
            $this->cache->invalidate();

            return;
        }

        if ($model->wasRecentlyCreated) {
            $this->cache->invalidateType($model->getTable());

            return;
        }

        $this->cache->invalidateDependencies([
            $this->cache->dependencyKey($model->getTable(), (string) $model->getKey()),
        ]);
        if ($model->wasChanged(['is_published', 'is_active', 'published_at', 'category_id', 'order', 'is_featured', 'price'])) {
            $this->cache->invalidateType($model->getTable());
        }
        $this->cache->invalidateIndexes();
    }

    public function deleted(Model $model): void
    {
        $this->invalidateSectionOwner($model);
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($model): void {
                if ($this->isSiteWide($model)) {
                    $this->cache->invalidate();
                } else {
                    $this->cache->invalidateType($model->getTable());
                }
            });
        }
        if ($this->isSiteWide($model)) {
            $this->cache->invalidate();

            return;
        }

        $this->cache->invalidateType($model->getTable());
    }

    public function restored(Model $model): void
    {
        $this->invalidateSectionOwner($model);
        if ($this->isSiteWide($model)) {
            $this->cache->invalidate();

            return;
        }

        $this->cache->invalidateType($model->getTable());
    }

    private function invalidateSectionOwner(Model $model): void
    {
        if (! $model instanceof PageSection) {
            return;
        }
        $owners = array_unique([(int) $model->getAttribute('page_id'), (int) $model->getRawOriginal('page_id')]);
        $dependencies = [];
        foreach ($owners as $id) {
            if ($id > 0) {
                $dependencies[] = $this->cache->dependencyKey('pages', $id);
            }
        }
        // A page with no published sections still depends on its section membership.
        $this->cache->invalidateDependencies($dependencies);
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->cache->invalidateDependencies($dependencies));
        }
    }

    /**
     * Layout/site-wide models bump the global namespace version instead of
     * relying on per-page dependencies.
     */
    private function isSiteWide(Model $model): bool
    {
        return in_array($model::class, (array) config('cms.page_cache_global_models', []), true);
    }
}
