<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Http\Controllers\FrontPageController;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Http\Controllers\Admin\Api\EntitySearchGetController;
use DbCms\Core\Http\Controllers\Admin\Auth\LoginGetController;
use DbCms\Core\Http\Controllers\Admin\Auth\LoginPostController;
use DbCms\Core\Http\Controllers\Admin\Auth\LogoutPostController;
use DbCms\Core\Http\Controllers\Admin\Auth\QuickLoginGetController;
use DbCms\Core\Http\Controllers\Admin\BlockPackages\BlockPackagesGetController;
use DbCms\Core\Http\Controllers\Admin\BlockPackages\BlockPackageTogglePostController;
use DbCms\Core\Http\Controllers\Admin\Connections\ConnectionsGetController;
use DbCms\Core\Http\Controllers\Admin\Connections\ConnectionsMigratePostController;
use DbCms\Core\Http\Controllers\Admin\Connections\ConnectionsMigrationStatusGetController;
use DbCms\Core\Http\Controllers\Admin\Connections\ConnectionsPutController;
use DbCms\Core\Http\Controllers\Admin\Connections\ConnectionsTestPostController;
use DbCms\Core\Http\Controllers\Admin\Dashboard\DashboardGetController;
use DbCms\Core\Http\Controllers\Admin\IntegrationGuides\IntegrationGuideGetController;
use DbCms\Core\Http\Controllers\Admin\IntegrationGuides\IntegrationGuideProgressPostController;
use DbCms\Core\Http\Controllers\Admin\IntegrationGuides\IntegrationGuidesGetController;
use DbCms\Core\Http\Controllers\Admin\Markets\LanguageDeleteController;
use DbCms\Core\Http\Controllers\Admin\Markets\LanguagePostController;
use DbCms\Core\Http\Controllers\Admin\Markets\LanguagePutController;
use DbCms\Core\Http\Controllers\Admin\Markets\LanguageTogglePostController;
use DbCms\Core\Http\Controllers\Admin\Markets\MarketDeleteController;
use DbCms\Core\Http\Controllers\Admin\Markets\MarketPostController;
use DbCms\Core\Http\Controllers\Admin\Markets\MarketPutController;
use DbCms\Core\Http\Controllers\Admin\Markets\MarketsGetController;
use DbCms\Core\Http\Controllers\Admin\Markets\MarketTogglePostController;
use DbCms\Core\Http\Controllers\Admin\Media\MediaDeleteController;
use DbCms\Core\Http\Controllers\Admin\Media\MediaGetController;
use DbCms\Core\Http\Controllers\Admin\Media\MediaImportPostController;
use DbCms\Core\Http\Controllers\Admin\Media\MediaPutController;
use DbCms\Core\Http\Controllers\Admin\Media\MediaUploadPostController;
use DbCms\Core\Http\Controllers\Admin\Menus\MenuDeleteController;
use DbCms\Core\Http\Controllers\Admin\Menus\MenuItemDeleteController;
use DbCms\Core\Http\Controllers\Admin\Menus\MenuItemPostController;
use DbCms\Core\Http\Controllers\Admin\Menus\MenuPostController;
use DbCms\Core\Http\Controllers\Admin\Menus\MenusGetController;
use DbCms\Core\Http\Controllers\Admin\Menus\MenuZonePostController;
use DbCms\Core\Http\Controllers\Admin\Modules\ModuleImpactGetController;
use DbCms\Core\Http\Controllers\Admin\Modules\ModuleInstallPostController;
use DbCms\Core\Http\Controllers\Admin\Modules\ModulesGetController;
use DbCms\Core\Http\Controllers\Admin\Modules\ModuleTogglePostController;
use DbCms\Core\Http\Controllers\Admin\Modules\ModuleUninstallPostController;
use DbCms\Core\Http\Controllers\Admin\ModuleSettings\ModuleSettingsGetController;
use DbCms\Core\Http\Controllers\Admin\ModuleSettings\ModuleSettingsPutController;
use DbCms\Core\Http\Controllers\Admin\Notifications\NotificationEmailPreferencePostController;
use DbCms\Core\Http\Controllers\Admin\Notifications\NotificationReadPostController;
use DbCms\Core\Http\Controllers\Admin\Notifications\NotificationsGetController;
use DbCms\Core\Http\Controllers\Admin\Notifications\NotificationsMarkAllReadPostController;
use DbCms\Core\Http\Controllers\Admin\Orchestration\ApprovePostController;
use DbCms\Core\Http\Controllers\Admin\Orchestration\CancelPostController;
use DbCms\Core\Http\Controllers\Admin\Orchestration\ResumePostController;
use DbCms\Core\Http\Controllers\Admin\Orchestration\ReviewGetController;
use DbCms\Core\Http\Controllers\Admin\Orchestration\ShowGetController;
use DbCms\Core\Http\Controllers\Admin\Seo\FilterDeleteController;
use DbCms\Core\Http\Controllers\Admin\Seo\FilterPostController;
use DbCms\Core\Http\Controllers\Admin\Seo\FilterPutController;
use DbCms\Core\Http\Controllers\Admin\Seo\FiltersGetController;
use DbCms\Core\Http\Controllers\Admin\Seo\SeoAuditGetController;
use DbCms\Core\Http\Controllers\Admin\SetLocaleGetController;
use DbCms\Core\Http\Controllers\Admin\SetLocalePostController;
use DbCms\Core\Http\Controllers\Admin\Settings\PrivacyGetController;
use DbCms\Core\Http\Controllers\Admin\Settings\PrivacyPutController;
use DbCms\Core\Http\Controllers\Admin\Settings\SettingsGetController;
use DbCms\Core\Http\Controllers\Admin\Settings\SettingsPutController;
use DbCms\Core\Http\Controllers\Admin\Themes\ThemeActivatePostController;
use DbCms\Core\Http\Controllers\Admin\Themes\ThemesGetController;
use DbCms\Core\Http\Controllers\Admin\Tokens\ClientRevokePostController;
use DbCms\Core\Http\Controllers\Admin\Tokens\M2mClientPostController;
use DbCms\Core\Http\Controllers\Admin\Tokens\PersonalTokenPostController;
use DbCms\Core\Http\Controllers\Admin\Tokens\TokenCreateGetController;
use DbCms\Core\Http\Controllers\Admin\Tokens\TokenRevokePostController;
use DbCms\Core\Http\Controllers\Admin\Tokens\TokensGetController;
use DbCms\Core\Http\Controllers\Admin\Users\UserDeleteController;
use DbCms\Core\Http\Controllers\Admin\Users\UserPostController;
use DbCms\Core\Http\Controllers\Admin\Users\UserRolePostController;
use DbCms\Core\Http\Controllers\Admin\Users\UsersGetController;
use DbCms\Core\Http\Controllers\Cms\ThemeAssetGetController;
use DbCms\Core\Http\Controllers\Geo\GeoPreferencePostController;
use DbCms\Core\Http\Controllers\Install\AdminGetController;
use DbCms\Core\Http\Controllers\Install\AdminStorePostController;
use DbCms\Core\Http\Controllers\Install\CompleteGetController;
use DbCms\Core\Http\Controllers\Install\DatabaseGetController;
use DbCms\Core\Http\Controllers\Install\DatabaseSavePostController;
use DbCms\Core\Http\Controllers\Install\DatabaseTestPostController;
use DbCms\Core\Http\Controllers\Install\MigrationGetController;
use DbCms\Core\Http\Controllers\Install\MigrationsPostController;
use DbCms\Core\Http\Controllers\Install\RequirementsGetController;
use DbCms\Core\Http\Controllers\Media\UploadsServeController;
use DbCms\Core\Http\Controllers\Seo\LlmsFullGetController;
use DbCms\Core\Http\Controllers\Seo\LlmsGetController;
use DbCms\Core\Http\Controllers\Seo\RegionalSitemapGetController;
use DbCms\Core\Http\Controllers\Seo\RobotsGetController;
use DbCms\Core\Http\Controllers\Seo\SitemapIndexGetController;
use DbCms\Core\Http\Controllers\Seo\SitemapPartGetController;
use DbCms\Core\Http\Controllers\Seo\SitemapSegmentGetController;
use DbCms\Core\Http\Middleware\CachePublicPage;
use DbCms\Core\Http\Middleware\CheckPermission;
use DbCms\Core\Http\Middleware\EnsureAdminAuthenticated;
use DbCms\Core\Http\Middleware\EnsureSeoMeta;
use DbCms\Core\Http\Middleware\RedirectIfInstalled;
use DbCms\Core\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use DbCms\Core\Http\Middleware\SetBackofficeLocale;
use DbCms\Core\Models\Market;
use DbCms\Core\Routing\FrontRoutePatterns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==========================================
// Web Installer Wizard (/install)
// ==========================================
Route::prefix('install')->middleware(['web', 'throttle:10,1', RedirectIfInstalled::class])->group(function (): void {
    Route::get('/', RequirementsGetController::class)->name('install.step1');
    Route::get('/database', DatabaseGetController::class)->name('install.step2');
    Route::post('/database/test', DatabaseTestPostController::class)->name('install.test-db');
    Route::post('/database/save', DatabaseSavePostController::class)->name('install.save-db');
    Route::get('/migrate', MigrationGetController::class)->name('install.step3');
    Route::post('/migrate/run', MigrationsPostController::class)->name('install.run-migrations');
    Route::get('/admin', AdminGetController::class)->name('install.step4');
    Route::post('/admin/store', AdminStorePostController::class)->name('install.store-admin');
    Route::get('/complete', CompleteGetController::class)->name('install.step5');
});

// ==========================================
// Generative Engine Optimization (GEO) & SEO
// ==========================================
// 1. Global root endpoints
Route::get('/llms.txt', LlmsGetController::class)->name('geo.llms');
Route::get('/llms-full.txt', LlmsFullGetController::class)->name('geo.llms-full');

// Sitemap index → segmented child sitemaps (pages/posts/products/galleries),
// dynamic robots.txt and the blog RSS feed. All cached with the page-cache version.
Route::get('/sitemap.xml', SitemapIndexGetController::class)->name('seo.sitemap');
Route::get('/sitemaps/{segment}.xml', SitemapSegmentGetController::class)->name('seo.sitemap.segment');
Route::get('/sitemap-part-{part}.xml', SitemapPartGetController::class)->where('part', '[1-9][0-9]*');
Route::get('/robots.txt', RobotsGetController::class)->name('seo.robots');

// Uploads fallback: static files are served directly by the web server, so this
// only runs for files missing locally and resolves them from external storage.
Route::get('/uploads/{path}', UploadsServeController::class)
    ->where('path', '.*')
    ->name('media.uploads');

// 2. Localized market & locale scoped endpoints
Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class, EnsureSeoMeta::class, CachePublicPage::class])->group(function (): void {
    $adminPath = (string) config('cms.admin_path', 'admin');
    $blocked = "admin|{$adminPath}|api|forms|geo|install|media|up";

    Route::prefix('{market}/{locale}')
        ->where(['market' => "^(?!{$blocked})[a-zA-Z0-9_\-]+$", 'locale' => FrontRoutePatterns::locale()])
        ->group(function (): void {
            Route::get('/llms.txt', LlmsGetController::class)->name('geo.market.llms');
            Route::get('/llms-full.txt', LlmsFullGetController::class)->name('geo.market.llms-full');
            Route::get('/sitemap.xml', RegionalSitemapGetController::class)->name('seo.market.sitemap');
            Route::get('/sitemap-part-{part}.xml', SitemapPartGetController::class)->where('part', '[1-9][0-9]*');

        });

    Route::prefix('{locale}')
        ->where(['locale' => "^(?!{$blocked}|blog|galeria|gallery|shop|tienda)[a-zA-Z]{2,5}$"])
        ->group(function (): void {
            Route::get('/llms.txt', LlmsGetController::class);
            Route::get('/llms-full.txt', LlmsFullGetController::class);
            Route::get('/sitemap.xml', RegionalSitemapGetController::class);
            Route::get('/sitemap-part-{part}.xml', SitemapPartGetController::class)->where('part', '[1-9][0-9]*');

        });
});

// Visitor Geo Preference Cookie Handler
Route::post('/geo/set-preference', GeoPreferencePostController::class)->name('geo.set-preference');

// Backoffice theme assets (served from the installed theme package's assets directory)
Route::get('/theme-assets/{theme}/{path}', ThemeAssetGetController::class)
    ->where('path', '.*')
    ->name('theme.asset');

// ==========================================
// Backoffice Administration Suite
// ==========================================
$adminPath = config('cms.admin_path', 'admin');

if ($adminPath !== 'admin') {
    Route::get('/admin', fn () => redirect("/{$adminPath}/login"));
}

Route::prefix($adminPath)->name('admin.')->middleware([SetBackofficeLocale::class])->group(function (): void {
    // Guest Auth Routes
    Route::get('/login', LoginGetController::class)->name('login');
    Route::post('/login', LoginPostController::class)->name('login.submit');
    Route::get('/quick-login/{role}', QuickLoginGetController::class)->name('quick-login');
    Route::post('/logout', LogoutPostController::class)->name('logout');

    // Backoffice language switch — deliberately available to guests too, so the
    // login screen can be read in the visitor's language. It only stores a
    // whitelisted session preference.
    Route::get('/locale/{locale}', SetLocaleGetController::class)->name('locale.switch');
    Route::post('/locale', SetLocalePostController::class)->name('locale.update');

    // Authenticated Backoffice Routes
    Route::middleware([EnsureAdminAuthenticated::class])->group(function (): void {
        Route::get('/', DashboardGetController::class)->name('dashboard');
        Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'));

        // Local setup guides remain readable while their integration is disabled.
        Route::get('/modules/{identifier}/guides', IntegrationGuidesGetController::class)->name('modules.guides.index');
        Route::get('/modules/{identifier}/guides/{guide}', IntegrationGuideGetController::class)->name('modules.guides.show');
        Route::post('/modules/{identifier}/guides/{guide}/progress', IntegrationGuideProgressPostController::class)->name('modules.guides.progress');

        // Generic AJAX search for the reusable searchable select component
        Route::get('/api/entity-search', EntitySearchGetController::class)->name('api.entity-search');

        // SEO & GEO Live Audit
        Route::middleware([CheckPermission::class.':manage:seo'])->group(function (): void {
            Route::get('/seo/audit', SeoAuditGetController::class)->name('seo.audit');
            Route::get('/seo', fn () => redirect()->route('admin.seo.audit'));

            // Curated filter landings (dynamic filter/search pages with SEO text).
            Route::get('/seo/filters', FiltersGetController::class)->name('seo.filters.index');
            Route::post('/seo/filters', FilterPostController::class)->name('seo.filters.store');
            Route::put('/seo/filters/{filterPage}', FilterPutController::class)->name('seo.filters.update');
            Route::delete('/seo/filters/{filterPage}', FilterDeleteController::class)->name('seo.filters.destroy');
        });

        // Module Management
        Route::middleware([CheckPermission::class.':manage:modules'])->group(function (): void {
            Route::get('/modules', ModulesGetController::class)->name('modules.index');
            Route::post('/modules/{identifier}/install', ModuleInstallPostController::class)->name('modules.install');
            Route::post('/modules/{identifier}/uninstall', ModuleUninstallPostController::class)->name('modules.uninstall');
            Route::post('/modules/{identifier}/toggle', ModuleTogglePostController::class)->name('modules.toggle');
            Route::get('/modules/{identifier}/impact', ModuleImpactGetController::class)->name('modules.impact');
            Route::get('/modules/{identifier}/settings', ModuleSettingsGetController::class)->name('modules.settings');
            Route::put('/modules/{identifier}/settings', ModuleSettingsPutController::class)->name('modules.settings.update');

            // Markets & Locales
            Route::get('/markets', MarketsGetController::class)->name('markets.index');
            Route::post('/markets', MarketPostController::class)->name('markets.store');
            Route::put('/markets/{market}', MarketPutController::class)->name('markets.update');
            Route::post('/markets/{market}/toggle', MarketTogglePostController::class)->name('markets.toggle');
            Route::delete('/markets/{market}', MarketDeleteController::class)->name('markets.destroy');
            Route::post('/languages', LanguagePostController::class)->name('languages.store');
            Route::put('/languages/{language}', LanguagePutController::class)->name('languages.update');
            Route::post('/languages/{language}/toggle', LanguageTogglePostController::class)->name('languages.toggle');
            Route::delete('/languages/{language}', LanguageDeleteController::class)->name('languages.destroy');

            // General Site & Company Settings
            Route::get('/settings', SettingsGetController::class)->name('settings.index');
            Route::put('/settings', SettingsPutController::class)->name('settings.update');
            Route::get('/settings/privacy', PrivacyGetController::class)->name('settings.privacy');
            Route::put('/settings/privacy', PrivacyPutController::class)->name('settings.privacy.update');
        });

        // External Connections (AWS S3 / OVH / Cloudflare R2 document storage)
        Route::middleware([CheckPermission::class.':manage:storage'])->group(function (): void {
            Route::get('/connections', ConnectionsGetController::class)->name('connections.index');
            Route::put('/connections', ConnectionsPutController::class)->name('connections.update');
            Route::post('/connections/test', ConnectionsTestPostController::class)->name('connections.test');
            Route::post('/connections/migrate', ConnectionsMigratePostController::class)->name('connections.migrate');
            Route::get('/connections/migration/status', ConnectionsMigrationStatusGetController::class)->name('connections.migration.status');
        });

        // User & Role Management
        Route::middleware([CheckPermission::class.':manage:users'])->group(function (): void {
            Route::get('/users', UsersGetController::class)->name('users.index');
            Route::post('/users', UserPostController::class)->name('users.store');
            Route::post('/users/{user}/role', UserRolePostController::class)->name('users.update-role');
            Route::delete('/users/{user}', UserDeleteController::class)->name('users.destroy');
        });

        // API & MCP Tokens / M2M Clients Management
        Route::middleware([CheckPermission::class.':manage:tokens'])->group(function (): void {
            Route::get('/tokens', TokensGetController::class)->name('tokens.index');
            Route::get('/tokens/create', TokenCreateGetController::class)->name('tokens.create');
            Route::post('/tokens/personal', PersonalTokenPostController::class)->name('tokens.store-personal');
            Route::post('/tokens/m2m', M2mClientPostController::class)->name('tokens.store-m2m');
            Route::post('/tokens/{id}/revoke', TokenRevokePostController::class)->name('tokens.revoke');
            Route::post('/tokens/clients/{id}/revoke', ClientRevokePostController::class)->name('tokens.revoke-client');
        });

        // Menus & Navigation Zones
        Route::middleware([CheckPermission::class.':manage:menus'])->group(function (): void {
            Route::get('/menus', MenusGetController::class)->name('menus.index');
            Route::post('/menus', MenuPostController::class)->name('menus.store');
            Route::post('/menus/zones', MenuZonePostController::class)->name('menus.zones.store');
            Route::delete('/menus/{menu}', MenuDeleteController::class)->name('menus.destroy');
            Route::post('/menus/{menu}/items', MenuItemPostController::class)->name('menus.store-item');
            Route::delete('/menus/items/{item}', MenuItemDeleteController::class)->name('menus.destroy-item');
        });

        // Themes & Layout Switcher
        Route::middleware([CheckPermission::class.':manage:blocks'])->prefix('block-packages')->name('block-packages.')->group(function (): void {
            Route::get('/', BlockPackagesGetController::class)->name('index');
            Route::post('/{identifier}/toggle', BlockPackageTogglePostController::class)->name('toggle');
        });

        Route::middleware([CheckPermission::class.':manage:themes'])->group(function (): void {
            Route::get('/themes', ThemesGetController::class)->name('themes.index');
            Route::post('/themes/{theme}/activate', ThemeActivatePostController::class)->name('themes.activate');
        });

        // Media Manager API for WYSIWYG & Filemanager Modal
        Route::get('/media', MediaGetController::class)->middleware(CheckPermission::class.':manage:media')->name('media.index');
        Route::middleware([CheckPermission::class.':manage:media'])->group(function (): void {
            Route::post('/media/import', MediaImportPostController::class)->name('media.import');
            Route::post('/media/upload', MediaUploadPostController::class)->name('media.upload');
            Route::put('/media/{id}', MediaPutController::class)->name('media.update');
            Route::delete('/media/{filename}', MediaDeleteController::class)->name('media.destroy')->where('filename', '.*');
        });

        // Notifications API
        Route::get('/notifications', NotificationsGetController::class)->name('notifications.index');
        Route::post('/notifications/email-preference', NotificationEmailPreferencePostController::class)->name('notifications.email-preference');
        Route::post('/notifications/mark-all-read', NotificationsMarkAllReadPostController::class)->name('notifications.mark-all-read');
        Route::post('/notifications/{id}/read', NotificationReadPostController::class)->name('notifications.read');
    });
});

// ==========================================
// Root Domain Dynamic Routing
// ==========================================
Route::get('/', function (Request $request, LocalizationServiceInterface $localization) {
    $hasMarkets = $localization->hasMultipleMarkets();
    $hasLangs = $localization->hasMultipleLanguages();

    $defaultMarket = Market::where('is_default', true)->where('is_active', true)->first()
        ?? Market::where('is_active', true)->first();
    $marketCode = $defaultMarket ? $defaultMarket->code : 'es';
    $locale = $defaultMarket ? $defaultMarket->default_locale : 'es';

    // If single market
    if (! $hasMarkets) {
        if (! $hasLangs) {
            return app(FrontPageController::class)->show($request);
        }

        return redirect("/{$locale}");
    }

    // If multi-market: redirect to /{market}/{locale}
    return redirect("/{$marketCode}/{$locale}");

})->middleware(['web', ResolveMarketAndLocaleMiddleware::class]);

Route::prefix($adminPath.'/executions')->name('admin.executions.')->middleware(['web', 'auth', EnsureAdminAuthenticated::class, SetBackofficeLocale::class])->group(function (): void {
    Route::get('/{execution}', ReviewGetController::class)->name('review');
    Route::get('/{execution}/status', ShowGetController::class)->name('show');
    Route::post('/{execution}/approve', ApprovePostController::class)->name('approve');
    Route::post('/{execution}/resume', ResumePostController::class)->name('resume');
    Route::post('/{execution}/cancel', CancelPostController::class)->name('cancel');
});
