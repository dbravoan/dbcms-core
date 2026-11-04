<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Cms;
use DbCms\Core\Console\Commands\CachePurgeCommand;
use DbCms\Core\Console\Commands\CmsMaintenanceCommand;
use DbCms\Core\Console\Commands\CreateM2mClientCommand;
use DbCms\Core\Console\Commands\ExecutionPrivacyCommand;
use DbCms\Core\Console\Commands\InstallCmsCommand;
use DbCms\Core\Console\Commands\IssueMcpTokenCommand;
use DbCms\Core\Console\Commands\MigrateCmsCommand;
use DbCms\Core\Console\Commands\MigrateMediaToExternalCommand;
use DbCms\Core\Console\Commands\SearchReindexCommand;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\PagesServiceProvider;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/** Laravel package integration; business contributions retain their owners. */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComposerPackages::class);
        $this->app->tag(ExternalContentPolicy::class, 'cms.csp.connect');
        foreach (glob(Cms::path('config/*.php')) ?: [] as $configuration) {
            $this->mergeConfigFrom($configuration, pathinfo($configuration, PATHINFO_FILENAME));
        }
        $coreViews = Cms::path('resources/views');
        config(['view.paths' => array_values(array_unique([...(array) config('view.paths', [resource_path('views')]), $coreViews]))]);
        View::addLocation($coreViews);
        $this->loadTranslationsFrom(Cms::path('lang'), 'cms');
        $this->app->afterResolving('translator', static function ($translator): void {
            $translator->addPath(Cms::path('lang'));
        });
        foreach ([
            BlockServiceProvider::class, AppServiceProvider::class,
            CmsCapabilitiesServiceProvider::class, IntegrationGuidesServiceProvider::class,
            FilterServiceProvider::class, FormFieldTypesServiceProvider::class,
            AuthServiceProvider::class, DomainServiceProvider::class,
            PassportServiceProvider::class, SearchServiceProvider::class,
            SeoServiceProvider::class, PagesServiceProvider::class,
            PrivacyServiceProvider::class,
        ] as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(Cms::path('database/migrations'));
        $this->commands([
            ExecutionPrivacyCommand::class,
            MigrateCmsCommand::class,
            InstallCmsCommand::class,
            CachePurgeCommand::class,
            CmsMaintenanceCommand::class,
            IssueMcpTokenCommand::class,
            CreateM2mClientCommand::class,
            MigrateMediaToExternalCommand::class,
            SearchReindexCommand::class,
        ]);
        Event::listen(DiagnosingHealth::class, static function (): void {
            if (is_file(storage_path('installed'))) {
                DB::select('SELECT 1');
            }
        });
        $this->app->booted(function (): void {
            $map = ['user' => User::class, 'page' => Page::class, 'filter_page' => FilterPage::class];
            foreach ($this->app->make(ComposerPackages::class)->modules() as $module) {
                foreach ((array) ($module['morphs'] ?? []) as $alias => $model) {
                    if (isset($map[$alias]) && $map[$alias] !== $model) {
                        throw new \DomainException('Duplicate persisted entity alias: '.$alias);
                    }
                    $map[$alias] = $model;
                }
            }
            foreach ($this->app->make(EntitySchemaRegistryInterface::class)->all() as $schema) {
                $map[$schema->type] = $schema->model;
            }
            Relation::morphMap($map);
        });
    }
}
