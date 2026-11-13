<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Blocks\Events\BlockPackageAvailabilityChanged;
use DbCms\Core\Cms;
use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Content\EntitySchemaRegistry;
use DbCms\Core\Content\PublicSemanticRegistry;
use DbCms\Core\Contracts\Services\AdminDashboardServiceInterface;
use DbCms\Core\Contracts\Services\AdminNavigationServiceInterface;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\BrandServiceInterface;
use DbCms\Core\Contracts\Services\EntitySearchServiceInterface;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Contracts\Services\InstallationAdminServiceInterface;
use DbCms\Core\Contracts\Services\InstallationDatabaseServiceInterface;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\Contracts\Services\InstallationProvisioningServiceInterface;
use DbCms\Core\Contracts\Services\InstallationViewServiceInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\MenuEditorServiceInterface;
use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Contracts\Services\ModuleSettingsAdministrationInterface;
use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\Contracts\Services\NotificationRecipientResolverInterface;
use DbCms\Core\Contracts\Services\PublishedSectionTextInterface;
use DbCms\Core\Contracts\Services\SearchAnalyticsInterface;
use DbCms\Core\Events\ModuleAvailabilityChanged;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Http\ViewComposers\AdminLayoutComposer;
use DbCms\Core\Http\ViewComposers\FrontendLayoutComposer;
use DbCms\Core\Http\ViewComposers\FrontofficeAdminBarComposer;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\Contracts\MaintenanceModeInterface;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Modules\ModuleSettingsExtensionRegistry;
use DbCms\Core\Modules\NullMaintenanceModeService;
use DbCms\Core\Notifications\NotificationRecipientPolicyRegistry;
use DbCms\Core\Observers\PageCacheObserver;
use DbCms\Core\Observers\UserSecurityObserver;
use DbCms\Core\Services\Admin\AdminDashboardService;
use DbCms\Core\Services\Admin\AdminNavigationService;
use DbCms\Core\Services\Admin\MenuEditorService;
use DbCms\Core\Services\Cms\AdminThemeService;
use DbCms\Core\Services\Cms\BrandService;
use DbCms\Core\Services\Cms\CacheDependencyCollector;
use DbCms\Core\Services\Cms\GeneralSettingsService;
use DbCms\Core\Services\Cms\LocalizationService;
use DbCms\Core\Services\Cms\ModuleNotificationService;
use DbCms\Core\Services\Cms\ModuleSettingsAdministrationService;
use DbCms\Core\Services\Cms\ModuleSettingsService;
use DbCms\Core\Services\Cms\NotificationRecipientResolverService;
use DbCms\Core\Services\Cms\PublicationBoundaryService;
use DbCms\Core\Services\Cms\ThemeManagerService;
use DbCms\Core\Services\Content\NoEditorialWorkflow;
use DbCms\Core\Services\Content\PublishedSectionTextService;
use DbCms\Core\Services\Install\InstallationAdminService;
use DbCms\Core\Services\Install\InstallationDatabaseService;
use DbCms\Core\Services\Install\InstallationEnvironmentService;
use DbCms\Core\Services\Install\InstallationProvisioningService;
use DbCms\Core\Services\Install\InstallationViewService;
use DbCms\Core\Services\Search\EntitySearchService;
use DbCms\Core\Services\Search\NoSearchAnalytics;
use DbCms\Core\Services\Security\PermissionCatalog;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EditorialWorkflowInterface::class, NoEditorialWorkflow::class);
        $this->app->singleton(MaintenanceModeInterface::class, NullMaintenanceModeService::class);
        $this->app->bind(SearchAnalyticsInterface::class, NoSearchAnalytics::class);
        $this->app->bind(MenuEditorServiceInterface::class, MenuEditorService::class);
        $this->app->scoped(PublishedSectionTextService::class);
        $this->app->bind(PublishedSectionTextInterface::class, fn () => $this->app->make(PublishedSectionTextService::class));
        $this->app->bind(ModuleSettingsAdministrationInterface::class, ModuleSettingsAdministrationService::class);
        $this->app->singleton(ModuleSettingsExtensionRegistry::class);
        $this->app->bind(InstallationEnvironmentServiceInterface::class, InstallationEnvironmentService::class);
        $this->app->bind(InstallationDatabaseServiceInterface::class, InstallationDatabaseService::class);
        $this->app->bind(InstallationProvisioningServiceInterface::class, InstallationProvisioningService::class);
        $this->app->bind(InstallationAdminServiceInterface::class, InstallationAdminService::class);
        $this->app->bind(InstallationViewServiceInterface::class, InstallationViewService::class);
        $this->app->singleton(PublicSemanticRegistry::class);
        $this->app->singleton(PublicationBoundaryService::class);
        $this->app->singleton(NotificationRecipientPolicyRegistry::class);
        $this->app->bind(NotificationRecipientResolverInterface::class, NotificationRecipientResolverService::class);
        $this->app->bind(AdminDashboardServiceInterface::class, AdminDashboardService::class);
        $this->app->singleton(LocalizationServiceInterface::class, LocalizationService::class);
        $this->app->singleton(GeneralSettingsServiceInterface::class, GeneralSettingsService::class);
        $this->app->singleton(ModuleSettingsServiceInterface::class, ModuleSettingsService::class);
        $this->app->singleton(ModuleNotificationServiceInterface::class, ModuleNotificationService::class);
        $this->app->singleton(ModuleManager::class);
        $this->app->singleton(AdminNavigationServiceInterface::class, AdminNavigationService::class);
        $this->app->singleton(AdminThemeServiceInterface::class, AdminThemeService::class);
        $this->app->singleton(BrandServiceInterface::class, BrandService::class);
        $this->app->singleton(EntitySchemaRegistryInterface::class, EntitySchemaRegistry::class);
        $this->app->singleton(EntitySearchServiceInterface::class, EntitySearchService::class);
        $this->app->singleton(ExternalStorageService::class);
        $this->app->singleton(CacheDependencyCollector::class);
        $this->app->make(ModuleManager::class)->registerConfiguredModules();
    }

    public function boot(): void
    {
        Event::listen([ModuleAvailabilityChanged::class, BlockPackageAvailabilityChanged::class], static function (ModuleAvailabilityChanged|BlockPackageAvailabilityChanged $event): void {
            DB::afterCommit(static function () use ($event): void {
                if ($event instanceof ModuleAvailabilityChanged) {
                    Artisan::call('route:clear');
                }
                Artisan::call('queue:restart');
            });
        });
        User::observe(UserSecurityObserver::class);
        // Public (unauthenticated) write endpoints — forms, comments, shop
        // checkout/inquiry/cart and geo preference — are throttled per IP.
        RateLimiter::for('public-writes', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('cms.public_writes_per_minute', 30))
        )->by($request->ip()));

        // Compiled assets resolve with root-relative URLs so the public page
        // cache stays host-agnostic: the same cached HTML works behind any
        // domain, port mapping or reverse proxy without leaking the origin
        // host of whichever request rendered the page first.
        Vite::createAssetPathsUsing(
            static fn (string $path, ?bool $secure = null): string => '/'.ltrim($path, '/')
        );

        // Enforce strict Eloquent behavior to prevent N+1 issues and unfillable attributes
        Model::shouldBeStrict(! $this->app->isProduction());

        // Safety net: a production application must never leak stack traces, even if
        // APP_DEBUG was left enabled by mistake in .env.
        if ($this->app->isProduction()) {
            config([
                'app.debug' => false,
                'logging.channels.stack.level' => config('logging.channels.stack.level', 'warning'),
            ]);
        }

        // Register the optional external (S3-compatible) disk from the backoffice
        // settings so documents can be stored on AWS / OVH / Cloudflare R2.
        try {
            $this->app->make(ExternalStorageService::class)->registerDisk();
        } catch (\Throwable) {
            // Settings not available yet (install / migrate): keep the default disk.
        }

        URL::defaults([
            'market' => 'es',
            'locale' => 'es',
        ]);

        if (! $this->app->isProduction()) {
            Passport::$validateKeyPermissions = false;
        }

        // Load Passport migrations
        $passportMigrations = base_path('vendor/laravel/passport/database/migrations');
        if (File::exists($passportMigrations)) {
            $this->loadMigrationsFrom($passportMigrations);
        }

        // Register themes view path
        $themeViewPaths = array_filter([resource_path('views/themes'), Cms::path('resources/views/themes')], is_dir(...));
        foreach (app(ComposerPackages::class)->all('dbcms-theme') as $id => $package) {
            View::addNamespace('theme-'.$id, [resource_path('views/themes/'.$id), $package['directory']]);
        }
        View::addNamespace('themes', array_values($themeViewPaths));

        // The front theme is selected at runtime via `cms.theme`; long-lived
        // processes (queue workers, tests) may switch it, so drop the cached view
        // resolution whenever the value changes (see ThemeManagerService::syncActiveTheme).
        if (class_exists(ThemeManagerService::class)) {
            $this->app->make(ThemeManagerService::class)->syncActiveTheme();
        }

        // Register isolated backoffice theme paths & component overrides
        /** @var AdminThemeServiceInterface $adminThemeService */
        $adminThemeService = $this->app->make(AdminThemeServiceInterface::class);
        $adminThemeService->registerThemeViewPaths();

        // Invalidate the public page cache whenever cacheable content changes.
        // Content is edited continuously, so freshness is event-driven (see
        // DbCms\Core\Services\Cms\PageCacheService for the namespace-version strategy).
        $this->app->booted(function (): void {
            $pageCacheObserver = $this->app->make(PageCacheObserver::class);
            $collector = $this->app->make(CacheDependencyCollector::class);

            foreach ((array) config('cms.page_cache_models', []) as $model) {
                if (! class_exists($model)) {
                    continue;
                }

                $model::observe($pageCacheObserver);

                // While a public page is rendered, record which cacheable entities it
                // reads so PageCacheService can invalidate only the affected pages.
                $model::retrieved(function (Model $retrieved) use ($collector): void {
                    // Read the key from the raw attributes: partial selects (and
                    // Model::shouldBeStrict) must not throw MissingAttributeException.
                    $key = $retrieved->getAttributes()[$retrieved->getKeyName()] ?? null;

                    if ($key !== null) {
                        $collector->record($retrieved->getTable(), $key);
                    }
                });
            }
        });

        // Register default admin & cms components namespace
        Blade::anonymousComponentPath(Cms::path('resources/views/components/cms'), 'cms');

        // Bind global brand identity & theme flavor dynamically to all views.
        // Resolved at render time (not once per container) so switching the active
        // theme within a process — e.g. tests, queue workers — stays correct.
        View::composer('*', function (\Illuminate\View\View $view): void {
            $brand = app(BrandServiceInterface::class)->current();

            $view->with([
                'cmsBrand' => $brand,
                // Brand identity (theme manifest) — what the UI shows as the product brand.
                'cmsBrandName' => $brand->name !== '' ? $brand->name : (string) config('cms.name', 'CMS'),
                // Site name (APP_NAME / cms.name) — used for SEO titles and site metadata.
                'cmsSiteName' => (string) config('cms.name', 'CMS'),
                'adminTheme' => $brand->theme,
            ]);
        });

        // Bind global admin layout context through View Composer
        View::composer(['admin.*', '*::admin.*'], AdminLayoutComposer::class);
        View::composer('components.frontoffice-admin-bar', FrontofficeAdminBarComposer::class);
        View::composer('components.admin.market-picker', function (\Illuminate\View\View $view): void {
            $markets = $this->app->make(LocalizationServiceInterface::class)->activeMarkets();
            $view->with('pickerMarkets', $markets);
            $view->with('pickerMarketLocales', $markets->mapWithKeys(static fn (Market $market): array => [
                (string) $market->id => $market->languages->where('is_active', true)->pluck('code')->all(),
            ])->all());
        });
        View::composer('components.admin.locale-tabs', function (\Illuminate\View\View $view): void {
            $view->with('languageFlags', $this->app->make(LocalizationServiceInterface::class)->activeLanguages()
                ->mapWithKeys(static fn (Language $language): array => [$language->code => $language->flag])->all());
        });

        // Bind global frontend presentation context through View Composer
        View::composer(['themes::*', 'theme-*::*', '*.partials.*'], FrontendLayoutComposer::class);

        // RBAC Gates
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isAdmin()) {
                return true;
            }

            return null;
        });

        // Every permission in the canonical catalogue is a Gate. Modules can
        // add permissions through their Composer/module manifests; the access
        // provisioner persists them and this loop makes them usable by views.
        $permissions = PermissionCatalog::definitions($this->app->make(ComposerPackages::class)->modules());
        foreach ($permissions as $permission => $label) {
            Gate::define($permission, static fn (User $user): bool => $user->hasPermission($permission));
        }
    }
}
