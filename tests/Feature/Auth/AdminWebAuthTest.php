<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\BrandServiceInterface;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities(['admin' => [], 'editor' => ['access:admin'], 'author' => ['access:admin'], 'marketing' => ['access:admin'], 'viewer' => ['access:admin']]);
});

test('admin login page renders its selected branding and enabled personas', function (): void {
    $response = $this->get(route('admin.login'));

    $response->assertStatus(200);
    $response->assertSee(app(BrandServiceInterface::class)->current()->name);
    $response->assertSee('Admin');
    $response->assertSee('Editor');
    $response->assertSee('Author');
    $response->assertSee('Marketing');
    $response->assertSee('Viewer');
});

test('admin user can authenticate with valid credentials', function (): void {
    $user = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->post(route('admin.login.submit'), [
        'email' => 'admin@db-cms.io',
        'password' => 'secret123',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user, 'web');
});

test('1-click persona quick-switcher logs in as requested role', function (string $role, string $expectedEmail): void {
    $response = $this->get(route('admin.quick-login', $role));

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated('web');
    $user = User::where('email', $expectedEmail)->firstOrFail();
    $this->assertAuthenticatedAs($user, 'web');
})->with([
    ['admin', 'admin@db-cms.io'],
    ['editor', 'editor@db-cms.io'],
    ['author', 'author@db-cms.io'],
    ['marketing', 'marketing@db-cms.io'],
    ['viewer', 'viewer@db-cms.io'],
]);

test('authenticated admin user can logout', function (): void {
    $user = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($user, 'web')->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest('web');
});
