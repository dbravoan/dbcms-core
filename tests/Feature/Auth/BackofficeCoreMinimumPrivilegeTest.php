<?php

declare(strict_types=1);

use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities(['author' => ['access:admin', 'manage:media']]);
    $this->author = User::where('email', 'author@db-cms.io')->firstOrFail();
});

test('an arbitrary role does not grant access to the administrative shell', function (): void {
    $user = User::factory()->create();
    $user->roles()->attach(Role::create(['slug' => 'empty-role', 'name' => 'Empty role']));
    $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

test('private selector sources require their resource permission', function (string $source): void {
    $this->actingAs($this->author)->getJson(route('admin.api.entity-search', ['source' => $source]))->assertForbidden();
})->with(['users', 'roles']);

test('media browsing requires management permission even for an administrative account', function (): void {
    $this->author->roles()->firstOrFail()->permissions()->detach(Permission::where('slug', 'manage:media')->value('id'));
    $this->actingAs($this->author)->getJson(route('admin.media.index'))->assertForbidden();
});

test('the dashboard hides user totals without user management permission', function (): void {
    $this->actingAs($this->author)->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_users'] === 0);
});

test('core feature settings require system administration permission', function (string $feature): void {
    $this->actingAs($this->author)->get(route('admin.settings.features.edit', ['feature' => $feature]))->assertForbidden();
    $this->putJson(route('admin.settings.features.update', ['feature' => $feature]), ['settings' => []])->assertForbidden();
})->with(['mcp', 'captcha', 'search', 'maintenance']);

test('core forms and their private submissions still require form management', function (string $route): void {
    $this->actingAs($this->author)->get(route($route))->assertForbidden();
})->with(['admin.forms.index', 'admin.forms.create', 'admin.forms.submissions']);
