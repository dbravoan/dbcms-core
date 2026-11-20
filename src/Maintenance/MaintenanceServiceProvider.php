<?php

declare(strict_types=1);

namespace DbCms\Core\Maintenance;

use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Cms;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Http\Middleware\EnsureAdminAuthenticated;
use DbCms\Core\Http\Middleware\SetBackofficeLocale;
use DbCms\Core\Maintenance\Capabilities\MaintenanceActionCatalog;
use DbCms\Core\Maintenance\Capabilities\MaintenanceActions;
use DbCms\Core\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use DbCms\Core\Maintenance\Services\MaintenanceService;
use DbCms\Core\Maintenance\Services\MaintenanceSettingsAdministrationService;
use DbCms\Core\Modules\Contracts\MaintenanceModeInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

final class MaintenanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MaintenanceService::class);
        $this->app->alias(MaintenanceService::class, MaintenanceModeInterface::class);
        $this->app->bind(MaintenanceSettingsAdministrationInterface::class, MaintenanceSettingsAdministrationService::class);
    }

    public function boot(): void
    {
        $views = Cms::path('resources/views/maintenance');
        $this->loadViewsFrom($views, 'maintenance');
        $this->app->make(AdminThemeServiceInterface::class)->registerModuleThemePaths('maintenance', $views);
        Route::prefix((string) config('cms.admin_path', 'admin'))->name('admin.')
            ->middleware(['web', EnsureAdminAuthenticated::class, SetBackofficeLocale::class])
            ->group(Cms::path('routes/maintenance/admin.php'));

        $this->app->booted(function (): void {
            MaintenanceActionCatalog::register(
                $this->app->make(CapabilityRegistry::class),
                $this->app->make(MaintenanceActions::class),
            );
        });
        ViewFacade::composer('components.frontoffice-admin-bar', function (View $view): void {
            if ($this->app->make(MaintenanceService::class)->isActive()) {
                $view->getFactory()->startPush('front_admin_status', view('maintenance::admin.front-status')->render());
            }
        });
    }
}
