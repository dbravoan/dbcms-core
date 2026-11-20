<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Menu;
use DbCms\Core\Models\MenuItemTranslation;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    Menu::create(['name' => 'Fixture menu', 'slug' => 'fixture-menu', 'location' => 'header', 'is_active' => true]);
});

test('menu items accept a title for every active language, including a new one', function (): void {
    Language::firstOrCreate(['code' => 'fr'], [
        'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true,
    ]);

    $menu = Menu::firstOrFail();
    $admin = User::first();

    $this->actingAs($admin, 'web')
        ->post(route('admin.menus.store-item', $menu), [
            'translations' => [
                'es' => ['title' => 'Inicio ES'],
                'fr' => ['title' => 'Accueil FR'],
            ],
            'url' => '/',
            'type' => 'custom',
        ])
        ->assertRedirect(route('admin.menus.index'));

    $item = $menu->items()->latest('id')->firstOrFail();
    $titles = MenuItemTranslation::where('menu_item_id', $item->id)->pluck('title', 'locale')->all();

    expect($titles)->toHaveKey('es')
        ->and($titles['es'])->toBe('Inicio ES')
        ->and($titles)->toHaveKey('fr')
        ->and($titles['fr'])->toBe('Accueil FR');
});

test('the menus screen shows a language tab per active language', function (): void {
    Language::firstOrCreate(['code' => 'fr'], [
        'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true,
    ]);

    $admin = User::first();
    $response = $this->actingAs($admin, 'web')->get(route('admin.menus.index'));

    $response->assertOk();

    // One tab + one title input per active language (es/en from the demo seeder
    // plus the French created here).
    foreach (app(LocalizationServiceInterface::class)->activeLocales() as $code) {
        $response->assertSee("itemLocale = '{$code}'", false);
        $response->assertSee("[title]'", false);
    }

    $response->assertSee('French', false);
});

test('the registry is reactive: a new active language shows up immediately', function (): void {
    $localization = app(LocalizationServiceInterface::class);
    $before = $localization->activeLocales();

    expect($before)->not->toContain('sv');

    // No manual cache invalidation: the model write does it.
    Language::firstOrCreate(['code' => 'sv'], [
        'name' => 'Swedish', 'native_name' => 'Svenska', 'direction' => 'ltr', 'is_active' => true,
    ]);

    expect($localization->activeLocales())->toContain('sv');
});

test('deactivating a language removes it from every reactive surface', function (): void {
    $fr = Language::firstOrCreate(['code' => 'fr'], [
        'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true,
    ]);

    $localization = app(LocalizationServiceInterface::class);
    expect($localization->activeLocales())->toContain('fr');

    $fr->update(['is_active' => false]);

    expect($localization->activeLocales())->not->toContain('fr');
});
