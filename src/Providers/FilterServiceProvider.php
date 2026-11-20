<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Filters\Contracts\FilterableModule;
use DbCms\Core\Filters\FilterAlternateUrls;
use DbCms\Core\Filters\FilterFacets;
use DbCms\Core\Filters\FilterModuleRegistry;
use DbCms\Core\Filters\FilterPanelSkin;
use DbCms\Core\Filters\FilterPathParser;
use DbCms\Core\Filters\FilterPathResolver;
use DbCms\Core\Filters\FilterSeoResolver;
use DbCms\Core\Filters\FilterUrlBuilder;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the friendly filter URL framework.
 *
 * The module registry is a singleton shared by every module (blog, shop, …);
 * they register their {@see FilterableModule}
 * implementation in their service provider's `boot()`.
 */
final class FilterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilterModuleRegistry::class);
        $this->app->singleton(FilterPathParser::class);
        $this->app->singleton(FilterUrlBuilder::class);
        $this->app->singleton(FilterSeoResolver::class);
        $this->app->singleton(FilterAlternateUrls::class);
        $this->app->singleton(FilterFacets::class);
        $this->app->singleton(FilterPathResolver::class);
        $this->app->singleton(FilterPanelSkin::class);
    }
}
