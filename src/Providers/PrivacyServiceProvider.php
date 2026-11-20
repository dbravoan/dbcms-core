<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Cms;
use DbCms\Core\Console\Commands\AccountPrivacyCommand;
use DbCms\Core\Console\Commands\PrivacyConfigureCommand;
use DbCms\Core\Console\Commands\PrivacyPruneCommand;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Http\Controllers\Privacy\ConsentController;
use DbCms\Core\Models\Language;
use DbCms\Core\Services\Cms\CookieConsentService;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

final class PrivacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(Cms::path('config/privacy.php'), 'privacy');
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('privacy:prune')->daily()->withoutOverlapping()->when(static fn (): bool => Schema::hasTable('privacy_consents'));
        });
        Route::middleware(['web', 'throttle:60,1'])->prefix('privacy')->group(function (): void {
            Route::get('consent', [ConsentController::class, 'show'])->name('privacy.consent.show');
            Route::post('consent', [ConsentController::class, 'store'])->name('privacy.consent.store');
        });
        View::composer('legal.*', static function (\Illuminate\View\View $view): void {
            if (! app(LegalPageService::class)->supportsLocale(app()->getLocale())) {
                abort(404);
            }
            $view->with('company', app(LegalPageService::class)->company());
            $view->with('cookieCategories', app(CookieConsentService::class)->getDisclosedCategories());
        });
        Page::saving(static function (Page $page): void {
            app(LegalPageService::class)->assertPageMutation($page, $page->getAttributes());
        });
        Page::deleting(static function (Page $page): void {
            app(LegalPageService::class)->assertPageMutation($page, [], true);
        });
        Language::saving(static function (Language $language): void {
            if ($language->is_active && setting('legal.company_name') && ! app(LegalPageService::class)->supportsLocale($language->code)) {
                throw ValidationException::withMessages(['code' => 'Prepare complete legal translations before activating this language.']);
            }
        });
        Language::saved(static function (Language $language): void {
            if ($language->is_active && setting('legal.company_name')) {
                app(LegalPageService::class)->prepareLocale($language->code);
            }
        });
        if ($this->app->runningInConsole()) {
            $this->commands([PrivacyPruneCommand::class, PrivacyConfigureCommand::class, AccountPrivacyCommand::class]);
        }
    }
}
