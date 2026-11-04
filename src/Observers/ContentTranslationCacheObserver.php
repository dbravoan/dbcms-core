<?php

declare(strict_types=1);

namespace DbCms\Core\Observers;

use DbCms\Core\Services\Cms\PageCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Translation-only MCP writes invalidate both HTML and public indexes. */
final class ContentTranslationCacheObserver
{
    public function __construct(private readonly PageCacheService $cache) {}

    public function saved(Model $translation): void
    {
        $this->cache->invalidate();
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->cache->invalidate());
        }
    }

    public function deleted(Model $translation): void
    {
        $this->saved($translation);
    }
}
