<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\Menu;
use DbCms\Core\Models\MenuItem;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    CmsFixtures::home();
    Menu::create(['name' => 'Fixture menu', 'slug' => 'fixture-menu', 'is_active' => true]);
});

test('page edit screen loads successfully with 200 OK without styling_json crashes', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $page = Page::with(['sections', 'translations'])->firstOrFail();

    $response = $this->actingAs($admin, 'web')
        ->get(route('admin.pages.edit', $page));

    $response->assertStatus(200);
    $response->assertSee('Editar página:');
    $response->assertSee('Modifica los bloques dinámicos');
});

test('menu items can be created and deleted cleanly using dynamic admin prefix', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $menu = Menu::firstOrFail();

    $response = $this->actingAs($admin, 'web')
        ->post(route('admin.menus.store-item', $menu), [
            'translations' => ['es' => ['title' => 'Nuevo Enlace Seguro']],
            'url' => '/seguridad',
            'type' => 'custom',
            'order' => 99,
        ]);

    $response->assertRedirect(route('admin.menus.index'));
    $response->assertSessionHas('success');

    $item = MenuItem::where('menu_id', $menu->id)->where('url', '/seguridad')->firstOrFail();
    expect($item->translations->where('locale', 'es')->first()?->title)->toBe('Nuevo Enlace Seguro');

    $deleteResponse = $this->actingAs($admin, 'web')
        ->delete(route('admin.menus.destroy-item', $item));

    $deleteResponse->assertRedirect(route('admin.menus.index'));
    $deleteResponse->assertSessionHas('success');
    expect(MenuItem::find($item->id))->toBeNull();
});
