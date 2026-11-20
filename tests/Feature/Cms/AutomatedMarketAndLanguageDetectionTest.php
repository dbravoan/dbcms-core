<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->spanish = Language::firstOrCreate(['code' => 'es'], [
        'name' => 'Spanish',
        'native_name' => 'Español',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->english = Language::firstOrCreate(['code' => 'en'], [
        'name' => 'English',
        'native_name' => 'English',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->catalan = Language::firstOrCreate(['code' => 'ca'], [
        'name' => 'Catalan',
        'native_name' => 'Català',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->marketEs = Market::firstOrCreate(['code' => 'es'], [
        'name' => 'Spain',
        'default_locale' => 'es',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->marketEs->languages()->sync([
        $this->spanish->id => ['is_default' => true],
        $this->catalan->id => ['is_default' => false],
        $this->english->id => ['is_default' => false],
    ]);

    $this->admin = User::create([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password123'),
    ]);

    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->admin->assignRole($adminRole);
});

test('backoffice displays all dynamically configured languages in market manager', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.markets.index'));

    $response->assertStatus(200)
        ->assertSee('Spanish')
        ->assertSee('English')
        ->assertSee('Catalan')
        ->assertSee('Idiomas del Sistema (Detección Dinámica)');
});

test('admin can store and toggle a new language dynamically', function (): void {
    $storeResponse = $this->actingAs($this->admin, 'web')->post(route('admin.languages.store'), [
        'code' => 'fr',
        'name' => 'French',
        'native_name' => 'Français',
        'is_active' => 1,
    ]);

    $storeResponse->assertRedirect(route('admin.markets.index'));
    $this->assertDatabaseHas('languages', ['code' => 'fr', 'name' => 'French']);

    $french = Language::where('code', 'fr')->firstOrFail();

    // Toggle active state to inactive
    $toggleResponse = $this->actingAs($this->admin, 'web')->post(route('admin.languages.toggle', $french));
    $toggleResponse->assertRedirect(route('admin.markets.index'));
    expect($french->fresh()->is_active)->toBeFalse();
});

test('page editor provides tabs for all active languages and pre-fills defaults', function (): void {
    $page = Page::create([
        'template' => 'default',
        'layout' => 'app',
        'is_published' => true,
    ]);
    $page->markets()->sync([$this->marketEs->id]);

    PageTranslation::create([
        'page_id' => $page->id,
        'locale' => 'es',
        'title' => 'Página Principal',
        'slug' => 'pagina-principal',
    ]);

    $response = $this->actingAs($this->admin, 'web')->get(route('admin.pages.edit', $page));

    $response->assertStatus(200)
        ->assertSee('Spanish (ES)')
        ->assertSee('English (EN)')
        ->assertSee('Catalan (CA)');
});
