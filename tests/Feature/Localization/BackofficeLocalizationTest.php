<?php

declare(strict_types=1);

use DbCms\Core\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('the backoffice defaults to spanish', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Panel principal');
});

test('an admin can switch the backoffice language and it persists per user', function (): void {
    $response = $this->actingAs($this->admin, 'web')
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.locale.update'), ['locale' => 'en']);

    $response->assertRedirect();

    expect($this->admin->fresh()->locale)->toBe('en');

    $dashboard = $this->actingAs($this->admin->fresh(), 'web')->get(route('admin.dashboard'));
    $dashboard->assertOk();
    $dashboard->assertSee('Dashboard');
    $dashboard->assertDontSee('Panel principal');
});

test('an invalid locale falls back to the default instead of breaking', function (): void {
    $response = $this->actingAs($this->admin, 'web')
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->post(route('admin.locale.update'), ['locale' => 'zz']);

    $response->assertRedirect();

    expect($this->admin->fresh()->locale)->toBe('es');
});

test('guests can switch the login language through the quick switch route', function (): void {
    $response = $this->from(route('admin.login'))->get(route('admin.locale.switch', ['locale' => 'en']));

    $response->assertRedirect(route('admin.login'));
    expect(session('backoffice_locale'))->toBe('en');
});
