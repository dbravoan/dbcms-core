<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $language = Language::create(['code' => 'es', 'name' => 'Español', 'native_name' => 'Español', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true]);
    $this->market = Market::create(['code' => 'es', 'name' => 'España', 'currency' => 'EUR', 'default_locale' => 'es', 'is_active' => true, 'is_default' => true]);
    $this->market->languages()->attach($language, ['is_default' => true]);
    $this->admin = User::factory()->create(['email' => 'smoke@example.test', 'password' => 'smoke-password']);
    $this->admin->roles()->attach(Role::create(['slug' => 'admin', 'name' => 'Admin']));
});

test('the core authenticates and publishes a localized page without optional content modules', function (): void {
    $this->post(route('admin.login.submit'), ['email' => $this->admin->email, 'password' => 'smoke-password'])
        ->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($this->admin);

    $this->post(route('admin.pages.store'), [
        'market_ids' => [$this->market->id],
        'is_published' => true,
        'translations' => ['es' => ['title' => 'Página de humo', 'slug' => 'smoke-page', 'content' => 'Contenido publicado por el núcleo.']],
    ])->assertRedirect(route('admin.pages.index'))->assertSessionHasNoErrors();

    $page = Page::query()->with('translations')->sole();
    expect($page->is_published)->toBeTrue()
        ->and($page->translations->sole()->slug)->toBe('smoke-page');
    $this->get('/es/es/smoke-page')->assertOk()->assertSee('Página de humo');
    $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
    $this->assertGuest();
});

test('an unprivileged account cannot create a core page', function (): void {
    $visitor = User::factory()->create();
    $this->actingAs($visitor)->post(route('admin.pages.store'), [
        'translations' => ['es' => ['title' => 'Not allowed', 'slug' => 'not-allowed']],
    ])->assertRedirect(route('admin.login'));
    expect(Page::query()->count())->toBe(0);
});
