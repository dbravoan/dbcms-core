<?php

declare(strict_types=1);

namespace DbCms\Core\Forms;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Capabilities\Resources\CapabilityResourceRegistry;
use DbCms\Core\Cms;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Forms\Blocks\CustomFormBlock;
use DbCms\Core\Forms\Capabilities\FormResources;
use DbCms\Core\Forms\Console\DeliverFormsCommand;
use DbCms\Core\Forms\Console\FormPrivacyCommand;
use DbCms\Core\Forms\Contracts\FormAdministrationServiceInterface;
use DbCms\Core\Forms\Contracts\FormEditorServiceInterface;
use DbCms\Core\Forms\Http\Controllers\FormSubmitController;
use DbCms\Core\Forms\Schema\FormEntitySchemas;
use DbCms\Core\Forms\Services\FormAdministrationService;
use DbCms\Core\Forms\Services\FormCookieDisclosure;
use DbCms\Core\Forms\Services\FormEditorService;
use DbCms\Core\Forms\Services\FormPresentationService;
use DbCms\Core\Http\Middleware\EnsureAdminAuthenticated;
use DbCms\Core\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use DbCms\Core\Http\Middleware\SetBackofficeLocale;
use DbCms\Core\Routing\FrontRoutePatterns;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/entity_search.php', 'entity_search.sources');
        $this->mergeConfigFrom(__DIR__.'/Config/delivery.php', 'forms_delivery');
        $this->app->singleton(FormCookieDisclosure::class);
        $this->app->tag(FormCookieDisclosure::class, 'cms.cookie-disclosures');
        $this->app->bind(FormAdministrationServiceInterface::class, FormAdministrationService::class);
        $this->app->bind(FormEditorServiceInterface::class, FormEditorService::class);
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('forms:deliver')->everyMinute()->withoutOverlapping()->when(static fn (): bool => Schema::hasTable('form_submission_actions'));
            $schedule->command('forms:privacy prune')->daily()->withoutOverlapping()->when(static fn (): bool => Schema::hasTable('form_submissions'));
        });
        if ($this->app->runningInConsole()) {
            $this->commands([FormPrivacyCommand::class, DeliverFormsCommand::class]);
        }
        $this->app->make(CapabilityResourceRegistry::class)->register(FormResources::descriptor());
        $path = Cms::path('resources/views/forms');
        $this->loadViewsFrom($path, 'forms');
        $this->app->make(AdminThemeServiceInterface::class)->registerModuleThemePaths('forms', $path);
        Route::prefix((string) config('cms.admin_path', 'admin'))->name('admin.')
            ->middleware(['web', EnsureAdminAuthenticated::class, SetBackofficeLocale::class])
            ->group(Cms::path('routes/forms/admin.php'));
        Blade::anonymousComponentNamespace('forms::components', 'forms');
        \Illuminate\Support\Facades\View::composer('forms::components.form-engine', function (View $view): void {
            $view->with($this->app->make(FormPresentationService::class)->data($view->getData()));
        });

        if ($this->app->bound(BlockRegistryInterface::class)) {
            $this->app->make(BlockRegistryInterface::class)->register(new CustomFormBlock);
        }

        if ($this->app->bound(EntitySchemaRegistryInterface::class)) {
            foreach (FormEntitySchemas::all() as $schema) {
                $this->app->make(EntitySchemaRegistryInterface::class)->register($schema);
            }
        }

        // Form submission endpoints
        $adminPath = (string) config('cms.admin_path', 'admin');
        $blocked = implode('|', array_map(static fn (string $prefix): string => preg_quote($prefix, '/'), array_unique([
            'admin', $adminPath, 'api', 'forms', 'geo', 'install', 'media', 'theme-assets', 'up',
        ])));
        $localePattern = "(?!(?:{$blocked})(?=/|$))".FrontRoutePatterns::locale();

        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class])
            ->prefix('{market}/{locale}')
            ->where(['market' => "(?!(?:{$blocked})(?=/|$))[a-zA-Z0-9_\-]+", 'locale' => $localePattern])
            ->group(function (): void {
                Route::post('/forms/{formSlug}/submit', [FormSubmitController::class, 'submit'])
                    ->name('forms.market.submit');
            });

        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class])
            ->prefix('{locale}')
            ->where(['locale' => $localePattern])
            ->group(function (): void {
                Route::post('/forms/{formSlug}/submit', [FormSubmitController::class, 'submit'])
                    ->name('forms.locale.submit');
            });

        Route::middleware(['web', ResolveMarketAndLocaleMiddleware::class])->post('/forms/{formSlug}/submit', [FormSubmitController::class, 'submit'])
            ->name('forms.submit');
    }
}
