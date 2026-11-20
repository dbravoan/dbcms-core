<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminNavigationServiceInterface;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Database\Seeders\CmsFoundationSeeder;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use DbCms\Core\Services\Cms\SiteSettingsService;
use DbCms\Core\Services\Security\AccessProvisioner;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(CmsFoundationSeeder::class);
    app(AccessProvisioner::class)->provision();
});

test('base features are core and the starter has no optional module requirement', function (): void {
    $catalog = app(ModuleManager::class)->discoverModules();
    foreach (['forms', 'mcp', 'search', 'maintenance', 'captcha'] as $feature) {
        expect($catalog)->not->toHaveKey($feature);
    }
    $manifest = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach (array_keys($manifest['require']) as $package) {
        expect($package)->not->toStartWith('dbravoan/dbcms-module-');
    }
    expect(core_setting('mcp', 'is_enabled'))->toBeTrue()
        ->and(core_setting('search', 'track_terms'))->toBeFalse()
        ->and(core_setting('search', 'is_indexable'))->toBeFalse()
        ->and(core_setting('captcha', 'driver'))->toBe('internal')
        ->and(core_setting('maintenance', 'is_enabled'))->toBeFalse();
});

test('feature secrets are encrypted and blank updates preserve credentials', function (): void {
    $settings = app(CoreFeatureSettings::class);
    $settings->set('captcha', 'secret_key', 'private-captcha-key');
    expect(setting('captcha.secret_key'))->not->toBe('private-captcha-key')
        ->and($settings->get('captcha', 'secret_key'))->toBe('private-captcha-key');
    $settings->set('captcha', 'secret_key', '');
    expect($settings->get('captcha', 'secret_key'))->toBe('private-captcha-key');
});

test('site and feature updates roll back together when validation fails', function (): void {
    app(GeneralSettingsServiceInterface::class)->set('general.site_name', 'Original');
    expect(fn () => app(SiteSettingsService::class)->update(['general.site_name' => 'Changed', 'mcp.rate_limit_per_minute' => 0]))
        ->toThrow(ValidationException::class);
    expect(setting('general.site_name'))->toBe('Original');
});

test('maintenance patches preserve operational flags and other settings', function (): void {
    app(CoreFeatureSettings::class)->setMany('maintenance', ['is_enabled' => false, 'show_countdown' => true, 'show_admin_link' => true]);
    app(SiteSettingsService::class)->update(['maintenance.message' => 'A new message']);
    expect(core_setting('maintenance', 'message'))->toBe('A new message')
        ->and(core_setting('maintenance', 'show_countdown'))->toBeTrue()
        ->and(core_setting('maintenance', 'show_admin_link'))->toBeTrue()
        ->and(core_setting('maintenance', 'is_enabled'))->toBeFalse();
});

test('feature validation rejects unsafe values through the shared settings writer', function (string $key, mixed $value): void {
    expect(fn () => app(SiteSettingsService::class)->update([$key => $value]))->toThrow(ValidationException::class);
})->with([
    ['captcha.min_score', 'not-a-number'], ['captcha.min_score', -1],
    ['search.results_per_page', 100000], ['search.min_results_to_index', 1000],
    ['search.retention_days', 0], ['mcp.unrecognized', true], ['search.track_terms', true],
]);

test('core settings screens use permissions and hide stored secrets in both brands', function (string $brand): void {
    config(['cms.admin_theme' => $brand]);
    app(CoreFeatureSettings::class)->set('captcha', 'secret_key', 'do-not-render-this');
    $viewer = User::factory()->create();
    $viewer->roles()->attach(Role::where('slug', 'viewer')->firstOrFail());
    $this->actingAs($viewer)->get(route('admin.settings.features.edit', ['feature' => 'captcha']))->assertForbidden();
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());
    $this->actingAs($admin)->get(route('admin.settings.features.edit', ['feature' => 'captcha']))->assertOk()->assertDontSee('do-not-render-this');
    $this->get(route('admin.settings.features.edit', ['feature' => 'mcp']))->assertOk()->assertSee(route('admin.tokens.create'));
    $navigation = collect(app(AdminNavigationServiceInterface::class)->getNavigationSections($admin))->flatMap(static fn ($section) => $section->items)->keyBy('id');
    expect($navigation['nav-feature-mcp']->active)->toBeTrue()->and($navigation['nav-settings']->active)->toBeFalse();
    $this->from(route('admin.settings.features.edit', ['feature' => 'captcha']))->put(route('admin.settings.features.update', ['feature' => 'captcha']), ['driver' => 'invalid', 'secret_key' => 'never-flash-this'])
        ->assertSessionHasErrors('driver')->assertSessionMissing('_old_input.secret_key');
})->with(['dbcms', 'fixture-admin']);
