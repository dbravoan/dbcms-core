<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\Setting;
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Core\Services\Cms\PageCacheService;
use DbCms\Core\Services\Cms\PublicPagePersonalization;
use DbCms\Core\Services\Cms\ThemeManagerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Tests\Support\ThemePackageFixture;

beforeEach(function (): void {
    $this->previousEnvironment = app()->environmentPath();
    $this->themeEnvironment = sys_get_temp_dir().'/dbcms-theme-env-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->themeEnvironment, 0700);
    File::put($this->themeEnvironment.'/.env', "APP_NAME=ThemeFixture\nCMS_THEME=fixture-theme\n");
    app()->useEnvironmentPath($this->themeEnvironment);
});

afterEach(function (): void {
    app()->useEnvironmentPath($this->previousEnvironment);
    File::deleteDirectory($this->themeEnvironment);
});

test('public asset entries include only the active frontend and backoffice brand', function (string $brand): void {
    config(['cms.theme' => 'fixture-theme', 'cms.admin_theme' => $brand]);

    expect(cms_asset_entries(true))->toBe([
        'resources/css/app.css',
        'resources/js/app.js',
        $brand === 'dbcms' ? 'vendor/dbravoan/dbcms-core/resources/admin-theme/theme.css' : 'vendor/fixture/admin-theme/theme.css',
        'vendor/fixture/fixture-theme/theme.css',
    ])->not->toContain('vendor/fixture/fixture-alternate/theme.css');
    expect(cms_asset_entries())->not->toContain('vendor/fixture/fixture-theme/theme.css');
})->with(['dbcms', 'fixture-admin']);

test('an installed third-party theme owns its layout and cannot borrow another theme layout', function (): void {
    $packages = new ThemePackageFixture;
    try {
        $path = $packages->front('independent-theme');
        config(['cms.theme' => 'independent-theme']);
        expect(app(ThemeManagerService::class)->getAvailableThemes())->toHaveKey('independent-theme');
        expect(app(FrontViewResolver::class)->layout())->toBe('theme-independent-theme::layouts.app');
        File::delete($path.'/layouts/app.blade.php');
        View::getFinder()->flush();
        expect(fn () => app(FrontViewResolver::class)->layout())->toThrow(DomainException::class, 'own layout');
    } finally {
        $packages->restore();
    }
});

test('activating a selected theme persists across cached configuration reloads and invalidates public html', function (): void {
    config(['cms.theme' => 'fixture-theme']);
    $cache = app(PageCacheService::class);
    $version = $cache->version();
    $environment = hash_file('sha256', app()->environmentFilePath());

    app(ThemeManagerService::class)->setActiveTheme('fixture-alternate');
    config(['cms.theme' => 'fixture-theme']);
    app(GeneralSettingsServiceInterface::class)->flushCache();

    expect(app(ThemeManagerService::class)->getActiveTheme())->toBe('fixture-alternate');
    expect($cache->version())->toBeGreaterThan($version);
    expect(hash_file('sha256', app()->environmentFilePath()))->toBe($environment);
});

test('uninstalled and invalid theme identifiers cannot be activated', function (): void {
    config(['cms.theme' => 'fixture-theme']);

    expect(fn () => app(ThemeManagerService::class)->setActiveTheme('uninstalled-theme'))->toThrow(ValidationException::class);
    expect(fn () => app(ThemeManagerService::class)->setActiveTheme('../fixture-theme'))->toThrow(ValidationException::class);
});

test('the next request refreshes a stored theme change even with stale worker configuration', function (): void {
    config(['cms.theme' => 'fixture-theme']);
    $manager = app(ThemeManagerService::class);
    expect($manager->getActiveTheme())->toBe('fixture-theme');

    // Another request updates the database while this worker retains its config.
    Setting::setValue(ThemeManagerService::ACTIVE_THEME_SETTING, 'fixture-alternate', 'appearance');
    $manager->syncActiveTheme();

    expect(config('cms.theme'))->toBe('fixture-alternate');
    expect(cms_asset_entries(true))->toContain('vendor/fixture/fixture-alternate/theme.css')->not->toContain('vendor/fixture/fixture-theme/theme.css');
});

test('theme cache invalidation respects the configured compiled view directory', function (): void {
    $directory = sys_get_temp_dir().'/dbcms-theme-views-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    file_put_contents($directory.'/compiled.php', '<?php /* disposable compiled view */');
    file_put_contents($directory.'/keep.txt', 'unrelated');
    config(['view.compiled' => $directory]);

    try {
        app(ThemeManagerService::class)->flushCompiledViews();

        expect(is_file($directory.'/compiled.php'))->toBeFalse();
        expect(is_file($directory.'/keep.txt'))->toBeTrue();
    } finally {
        File::deleteDirectory($directory);
    }
});

test('derived widgets use their module fallback without borrowing another frontend', function (): void {
    config(['cms.theme' => 'fixture-theme']);
    View::shouldReceive('exists')->with('theme-fixture-theme::shop.blocks.example')->andReturnFalse();
    View::shouldReceive('exists')->with('shop::blocks.example')->andReturnTrue();

    expect(app(FrontViewResolver::class)->resolve('shop.blocks.example', 'shop::blocks.example'))->toBe('shop::blocks.example');
});

test('public cache fragments accept installed custom themes and reject uninstalled headers', function (): void {
    $manager = Mockery::mock(ThemeManagerService::class);
    $manager->shouldReceive('getAvailableThemes')->andReturn(['client-site' => []]);
    app()->instance(ThemeManagerService::class, $manager);
    $personalization = new PublicPagePersonalization($manager);
    $render = static fn (string $view): string => '<header>'.$view.'</header>';

    expect($personalization->hydrate('<!--cms-fragment:theme-client-site::partials.header-->', 'csrf', $render))
        ->toBe('<header>theme-client-site::partials.header</header>');
    expect(fn () => $personalization->hydrate('<!--cms-fragment:theme-fixture-theme::partials.header-->', 'csrf', $render))
        ->toThrow(UnexpectedValueException::class);
});
