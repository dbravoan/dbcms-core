<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Blocks\Events\BlockPackageAvailabilityChanged;
use DbCms\Core\Search\Contracts\SearchSourceInterface;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use DbCms\Core\Search\SearchEngine;
use DbCms\Core\Search\SearchIndexer;
use DbCms\Core\Search\SearchSourceRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the cross-entity search framework.
 *
 * The source registry is a singleton shared by every module: each module
 * registers its {@see SearchSourceInterface}
 * implementations in its service provider's `boot()`, and the core indexes and
 * ranks them without knowing anything about the module.
 */
final class SearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(BlockPackageAvailabilityChanged::class, function (): void {
            $this->app->make(SearchIndexer::class)->indexAll();
        });
    }

    public function register(): void
    {
        $this->app->singleton(SearchSourceRegistryInterface::class, SearchSourceRegistry::class);
        $this->app->singleton(SearchIndexer::class);
        $this->app->singleton(SearchEngine::class);
    }
}
