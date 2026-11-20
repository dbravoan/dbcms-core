<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Cms;
use DbCms\Core\Content\PublicSemanticRegistry;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\SearchAnalyticsInterface;
use DbCms\Core\Http\Middleware\CachePublicPage;
use DbCms\Core\Http\Middleware\CanonicalModulePrefix;
use DbCms\Core\Http\Middleware\EnsureAdminAuthenticated;
use DbCms\Core\Http\Middleware\EnsureSeoMeta;
use DbCms\Core\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use DbCms\Core\Http\Middleware\SetBackofficeLocale;
use DbCms\Core\Routing\FrontRoutePatterns;
use DbCms\Core\Search\Blocks\SearchBoxBlock;
use DbCms\Core\Search\Capabilities\SearchActionCatalog;
use DbCms\Core\Search\Capabilities\SearchActions;
use DbCms\Core\Search\Capabilities\SearchNavigationCapability;
use DbCms\Core\Search\Console\SearchPrivacyCommand;
use DbCms\Core\Search\Contracts\SearchFrontPageServiceInterface;
use DbCms\Core\Search\Contracts\SearchSourceInterface;
use DbCms\Core\Search\Http\Controllers\SearchFrontController;
use DbCms\Core\Search\Services\SearchAnalyticsService;
use DbCms\Core\Search\Services\SearchCapabilityAnalyticsAdapter;
use DbCms\Core\Search\Services\SearchFrontPageService;
use DbCms\Core\Search\Services\SearchPublicUrls;
use DbCms\Core\Search\Services\SearchSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

/**
 * General front-office search.
 *
 * The core search owns no content: it serves the hub (`/{prefix}`) and the results
 * (`/{prefix}/{term}`) over every entity the installed modules indexed through
 * their {@see SearchSourceInterface} declarations.
 */
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SearchAnalyticsInterface::class, SearchCapabilityAnalyticsAdapter::class);
        $this->app->bind(SearchFrontPageServiceInterface::class, SearchFrontPageService::class);
    }

    public function boot(): void
    {
        $this->commands([SearchPrivacyCommand::class]);
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('search:privacy prune')->daily()->withoutOverlapping()
                ->when(static fn (): bool => Schema::hasTable('search_terms'));
        });
        $this->app->make(BlockRegistryInterface::class)->register($this->app->make(SearchBoxBlock::class));
        $this->app->make(PublicSemanticRegistry::class)->registerUrls($this->app->make(SearchPublicUrls::class));
        $this->app->booted(function (): void {
            $this->app->make(SearchNavigationCapability::class)->register($this->app->make(CapabilityRegistry::class));
            SearchActionCatalog::register(
                $this->app->make(CapabilityRegistry::class),
                $this->app->make(SearchActions::class),
            );
        });
        $path = Cms::path('resources/views/search');
        $this->loadViewsFrom($path, 'search');
        $this->app->make(AdminThemeServiceInterface::class)->registerModuleThemePaths('search', $path);
        Route::prefix((string) config('cms.admin_path', 'admin'))->name('admin.')
            ->middleware(['web', EnsureAdminAuthenticated::class, SetBackofficeLocale::class])
            ->group(Cms::path('routes/search/admin.php'));
        $this->registerPublicRoutes();

        \Illuminate\Support\Facades\View::composer('themes::components.search-box', function (View $view): void {
            $view->with('searchEnabled', true);
        });
        $this->composeFiltersInbox();

    }

    public function registerPublicRoutes(): void
    {
        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class, CanonicalModulePrefix::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
            /** @var LocalizationServiceInterface $localization */
            $localization = app(LocalizationServiceInterface::class);
            $pattern = implode('|', array_map(static fn (string $slug): string => preg_quote($slug, '#'), $localization->getAllModuleSlugs('search')));

            $adminPath = (string) config('cms.admin_path', 'admin');
            $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up|blog|tienda|shop|galeria|gallery|page";

            $routes = function (bool $named) use ($pattern): void {
                $index = Route::get('/{searchPrefix}', [SearchFrontController::class, 'hub'])
                    ->where(['searchPrefix' => $pattern]);

                $results = Route::get('/{searchPrefix}/{term}', [SearchFrontController::class, 'results'])
                    ->where(['searchPrefix' => $pattern, 'term' => '[^/]+']);

                if ($named) {
                    $index->name('search.index');
                    $results->name('search.results');
                }

                // The entity type segment is validated in the controller: the
                // registered sources depend on module boot order.
                $typed = Route::get('/{searchPrefix}/{term}/{entityType}', [SearchFrontController::class, 'results'])
                    ->where(['searchPrefix' => $pattern, 'term' => '[^/]+', 'entityType' => '[a-z0-9\-]{1,40}']);

                if ($named) {
                    $typed->name('search.type');
                }
            };

            // 1. Localized routes with {market}/{locale} prefix.
            Route::prefix('{market}/{locale}')
                ->where(['market' => "^(?!{$blocked})[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
                ->group(fn () => $routes(true));

            // 2. Localized routes with {locale} only (single-market setups).
            Route::prefix('{locale}')
                ->where(['locale' => "^(?!{$blocked}|{$pattern})[a-zA-Z]{2,5}$"])
                ->group(fn () => $routes(false));

            // 3. Fallback non-prefixed routes (1 market / 1 language).
            $routes(false);
        });
    }

    /**
     * Feeds the "Filtros y búsquedas" inbox with the activation state and the
     * real usage of the search, with the same core settings as public requests.
     */
    private function composeFiltersInbox(): void
    {
        $this->app->make('view')->composer('admin.filters.index', function (View $view): void {
            try {
                $locale = (string) app()->getLocale();
                $settings = app(SearchSettings::class);
                $indexer = app(SearchIndexer::class);
                $analytics = app(SearchAnalyticsService::class);
                $coverage = $indexer->stats($locale);

                $view->with('searchPanel', [
                    'url' => app(LocalizationServiceInterface::class)->frontUrl('search', '', $locale),
                    'indexable' => $settings->isIndexable(),
                    'tracks' => $settings->tracksTerms(),
                    'perPage' => $settings->resultsPerPage(),
                    'minResults' => $settings->minResultsToIndex(),
                    'coverage' => $coverage,
                    'total' => array_sum(array_column($coverage, 'indexed')),
                    'top' => $analytics->topTerms($locale, 8),
                    'unsatisfied' => $analytics->unsatisfiedTerms($locale, 8),
                ]);
            } catch (\Throwable) {
                // The inbox must render even before database provisioning.
            }
        });
    }
}
