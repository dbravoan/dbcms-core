<?php

declare(strict_types=1);

use DbCms\Core\Models\Menu;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    Menu::create(['name' => 'Fixture menu', 'slug' => 'fixture-menu', 'is_active' => true]);
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('menus index renders both menus and zones tabs with zone manager', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get('/admin/menus');
    $response->assertOk();

    // Verify tabs
    $response->assertSee('Estructura de Menús');
    $response->assertSee('Zonas de Navegación (Zone Manager)');

    // Verify registered zones
    $response->assertSee('Cabecera Principal (Header)');
    $response->assertSee('Pie de Página: Enlaces Rápidos');
    $response->assertSee('Barra Lateral (Sidebar)');

    // Verify responsive action buttons & mobile accordion elements
    $response->assertSee('data-row-detail', false);
    $response->assertSee('fa-chevron-down');
    $response->assertSee('md:hidden');
});

test('admin can assign menus to navigation zones via menu service', function (): void {
    $headerMenu = Menu::first();
    expect($headerMenu)->not->toBeNull();

    $response = $this->actingAs($this->admin, 'web')->post('/admin/menus/zones', [
        'zones' => [
            'header' => (string) $headerMenu->id,
            'footer' => '',
            'sidebar' => '',
        ],
    ]);

    $response->assertRedirect('/admin/menus?tab=zones');
    $response->assertSessionHas('success', 'Zonas de navegación actualizadas correctamente.');

    $headerMenu->refresh();
    expect($headerMenu->location)->toBe('header');
});
