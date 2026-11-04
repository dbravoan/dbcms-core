<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Services\Cms\PageCacheService;
use Illuminate\Console\Command;

final class CachePurgeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cms:cache:purge
                            {--force : Purge even when page caching is disabled}';

    /**
     * @var string
     */
    protected $description = 'Invalidate the public page cache (global namespace bump)';

    public function handle(PageCacheService $cache): int
    {
        if (! $cache->enabled() && ! $this->option('force')) {
            $this->warn('Public page caching is disabled; nothing to purge. Use --force to bump anyway.');

            return self::SUCCESS;
        }

        $before = $cache->version();
        $cache->invalidate();
        $after = $cache->version();

        $this->info("Public page cache purged (version {$before} → {$after}).");

        return self::SUCCESS;
    }
}
