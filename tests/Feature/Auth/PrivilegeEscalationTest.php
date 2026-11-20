<?php

declare(strict_types=1);

use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities([
        'admin' => [],
        'manager' => ['access:admin', 'manage:users'],
        'token_manager' => ['access:admin', 'manage:tokens'],
    ]);
});

test('a delegated user manager cannot create an administrator account', function (): void {
    $manager = User::where('email', 'manager@db-cms.io')->sole();
    $adminRole = Role::where('slug', 'admin')->sole();

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Escalated account',
        'email' => 'escalated@example.test',
        'password' => 'secret12345',
        'role_id' => $adminRole->getKey(),
    ])->assertSessionHasErrors('role_id');

    expect(User::where('email', 'escalated@example.test')->exists())->toBeFalse();
});

test('a user manager without token permission cannot open token management', function (): void {
    $manager = User::where('email', 'manager@db-cms.io')->sole();

    $this->actingAs($manager)->get(route('admin.tokens.index'))->assertForbidden();
});

test('a token manager cannot issue a token for another account', function (): void {
    $tokenManager = User::where('email', 'token_manager@db-cms.io')->sole();
    $admin = User::where('email', 'admin@db-cms.io')->sole();

    $this->actingAs($tokenManager)->post(route('admin.tokens.store-personal'), [
        'name' => 'Escalated token',
        'subject_type' => 'users',
        'subject_id' => $admin->getKey(),
        'scopes' => ['mcp:access'],
    ])->assertForbidden();
});

test('a token manager cannot grant scopes beyond their own permissions', function (): void {
    $tokenManager = User::where('email', 'token_manager@db-cms.io')->sole();

    $this->actingAs($tokenManager)->post(route('admin.tokens.store-personal'), [
        'name' => 'Over-scoped token',
        'subject_type' => 'users',
        'subject_id' => $tokenManager->getKey(),
        'scopes' => ['mcp:destructive'],
    ])->assertSessionHasErrors('scopes');
});

test('a delegated manager cannot remove an administrator or change their role', function (): void {
    $manager = User::where('email', 'manager@db-cms.io')->sole();
    $admin = User::where('email', 'admin@db-cms.io')->sole();
    User::factory()->create()->assignRole('admin');
    $role = Role::where('slug', 'manager')->sole();

    $this->actingAs($manager)->post(route('admin.users.update-role', $admin), ['role_id' => $role->id])
        ->assertSessionHasErrors('user');
    $this->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('user');

    expect($admin->fresh()?->isAdmin())->toBeTrue();
});

test('role changes preserve a human administrator', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->sole();
    $role = Role::where('slug', 'manager')->sole();
    $this->actingAs($admin)->post(route('admin.users.update-role', $admin), ['role_id' => $role->id])
        ->assertSessionHasErrors('user');

    expect($admin->fresh()?->isAdmin())->toBeTrue();
});
