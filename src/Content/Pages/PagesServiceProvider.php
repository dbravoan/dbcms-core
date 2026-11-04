<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\Pages\Contracts\PageDomainServiceInterface;
use DbCms\Core\Content\Pages\Contracts\PagePublicReaderInterface;
use DbCms\Core\Content\Pages\Http\Controllers\FrontPageController;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Content\Pages\Models\SectionTranslation;
use DbCms\Core\Content\Pages\Observers\PageSearchObserver;
use DbCms\Core\Content\Pages\Schema\PageEntitySchemas;
use DbCms\Core\Content\Pages\Search\PageSearchSource;
use DbCms\Core\Content\Pages\Services\PageDomainService;
use DbCms\Core\Content\Pages\Services\PagePublicReader;
use DbCms\Core\Content\Pages\Services\PageViewRegistry;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Http\Middleware\CachePublicPage;
use DbCms\Core\Http\Middleware\CanonicalModulePrefix;
use DbCms\Core\Http\Middleware\EnsureAdminAuthenticated;
use DbCms\Core\Http\Middleware\EnsureSeoMeta;
use DbCms\Core\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use DbCms\Core\Http\Middleware\SetBackofficeLocale;
use DbCms\Core\Observers\ContentTranslationCacheObserver;
use DbCms\Core\Routing\FrontRoutePatterns;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use DbCms\Core\View\Components\CmsBlock;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PageViewRegistry::class);
        $this->app->bind(PagePublicReaderInterface::class, PagePublicReader::class);
        $this->app->bind(PageDomainServiceInterface::class, PageDomainService::class);
    }

    public function boot(): void
    {
        $pageViews = $this->app->make(PageViewRegistry::class);
        foreach (['notice', 'privacy', 'cookies'] as $legal) {
            $pageViews->registerCore('legal.'.$legal, 'legal.'.$legal);
        }
        Blade::component(CmsBlock::class, 'cms::block');
        $this->app->make(Router::class)->middlewareGroup('cms.front', ['web', ResolveMarketAndLocaleMiddleware::class, EnsureSeoMeta::class]);
        $this->app->make(SearchSourceRegistryInterface::class)->register($this->app->make(PageSearchSource::class));
        Page::observe(PageSearchObserver::class);
        PageTranslation::observe(PageSearchObserver::class);
        PageSection::observe(PageSearchObserver::class);
        SectionTranslation::observe(PageSearchObserver::class);
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'pages');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->bound(EntitySchemaRegistryInterface::class)) {
            foreach (PageEntitySchemas::all() as $schema) {
                $this->app->make(EntitySchemaRegistryInterface::class)->register($schema);
            }
        }

        PageTranslation::observe(ContentTranslationCacheObserver::class);
        SectionTranslation::observe(ContentTranslationCacheObserver::class);
        $this->app->make(AdminThemeServiceInterface::class)->registerModuleThemePaths('pages', __DIR__.'/Resources/views');
        if (! $this->app->routesAreCached()) {
            Route::prefix((string) config('cms.admin_path', 'admin'))->name('admin.')
                ->middleware(['web', EnsureAdminAuthenticated::class, SetBackofficeLocale::class])
                ->group(__DIR__.'/Routes/admin.php');
        }
    }

    public static function registerPublicRoutes(): void
    {
        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class, CanonicalModulePrefix::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
            /** @var LocalizationServiceInterface $localization */
            $localization = app(LocalizationServiceInterface::class);
            $adminPath = (string) config('cms.admin_path', 'admin');
            $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up";
            $reserved = implode('|', array_map(static fn (string $part): string => preg_quote($part, '#'), [$adminPath, 'admin', 'api', 'geo', 'install', 'media', 'theme-assets', 'up', 'llms', 'llms-full', 'sitemap', 'sitemaps']));

            // 1. Localized page routes with market and locale prefix ({market}/{locale})
            Route::prefix('{market}/{locale}')
                ->where(['market' => "^(?!{$blocked}|(?:{$reserved})(?=/|$))[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
                ->group(function () use ($reserved): void {
                    Route::get('/', [FrontPageController::class, 'show'])->name('pages.home');
                    Route::get('', [FrontPageController::class, 'show']);

                    // Clean SEO Page URL: /{market}/{locale}/{slug}
                    Route::get('/{slug}', [FrontPageController::class, 'show'])
                        ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"])
                        ->name('pages.show');
                });

            // 2. Localized page routes with locale only ({locale})
            Route::prefix('{locale}')
                ->where(['locale' => "^(?!{$blocked}|{$reserved})[a-zA-Z]{2,5}$"])
                ->group(function () use ($reserved): void {
                    Route::get('/', [FrontPageController::class, 'show']);
                    Route::get('', [FrontPageController::class, 'show']);

                    Route::get('/{slug}', [FrontPageController::class, 'show'])
                        ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"]);
                });

            // 3. Fallback non-prefixed routes
            Route::get('/{slug}', [FrontPageController::class, 'show'])
                ->where(['slug' => "^(?!{$reserved}$)[a-zA-Z0-9_\-]+$"]);
        });
    }
}
