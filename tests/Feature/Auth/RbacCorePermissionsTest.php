<?php

declare(strict_types=1);

use DbCms\Core\Models\User;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities([
        'admin' => [],
        'editor' => ['access:admin', 'manage:pages'],
        'author' => ['access:admin'],
        'marketing' => ['access:admin'],
    ]);
});

test('admin has access to core backoffice management', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $this->actingAs($admin, 'web')->get(route('admin.dashboard'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.modules.index'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.users.index'))->assertStatus(200);
    $this->actingAs($admin, 'web')->get(route('admin.themes.index'))->assertStatus(200);
});

test('editor can access pages but is forbidden from user management', function (): void {
    $editor = User::where('email', 'editor@db-cms.io')->firstOrFail();

    $this->actingAs($editor, 'web')->get(route('admin.pages.index'))->assertStatus(200);
    $this->actingAs($editor, 'web')->get(route('admin.users.index'))->assertStatus(403);
});

test('author is forbidden from module management', function (): void {
    $author = User::where('email', 'author@db-cms.io')->firstOrFail();
    $this->actingAs($author, 'web')->get(route('admin.modules.index'))->assertStatus(403);
});

test('a marketing role is forbidden from user management', function (): void {
    $marketing = User::where('email', 'marketing@db-cms.io')->firstOrFail();
    $this->actingAs($marketing, 'web')->get(route('admin.users.index'))->assertStatus(403);
});

test('unauthenticated visitor is redirected to login when accessing admin dashboard', function (): void {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
});
