<?php

declare(strict_types=1);

use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Services\Security\AccessProvisioner;

test('a clean access provisioning run creates the permission catalogue and default roles', function (): void {
    $result = app(AccessProvisioner::class)->provision();

    expect($result['permissions'])->toBeGreaterThanOrEqual(20)
        ->and($result['roles'])->toBe(5)
        ->and(Permission::query()->where('slug', 'access:admin')->exists())->toBeTrue()
        ->and(Permission::query()->where('slug', 'use:ai')->exists())->toBeTrue()
        ->and(Permission::query()->where('slug', 'manage:galleries')->exists())->toBeTrue()
        ->and(Role::query()->whereIn('slug', ['admin', 'editor', 'author', 'marketing', 'viewer'])->count())->toBe(5);

    $editor = Role::query()->where('slug', 'editor')->sole();
    expect($editor->hasPermission('access:admin'))->toBeTrue()
        ->and($editor->hasPermission('manage:pages'))->toBeTrue()
        ->and($editor->hasPermission('use:ai'))->toBeTrue()
        ->and($editor->hasPermission('manage:users'))->toBeFalse();
});

test('access provisioning is idempotent and preserves custom grants', function (): void {
    $provisioner = app(AccessProvisioner::class);
    $provisioner->provision();

    $custom = Permission::query()->create(['slug' => 'custom:report', 'name' => 'Custom report']);
    Role::query()->where('slug', 'editor')->sole()->permissions()->attach($custom);

    $provisioner->provision();

    expect(Permission::query()->where('slug', 'custom:report')->exists())->toBeTrue()
        ->and(Role::query()->where('slug', 'editor')->sole()->permissions()->where('slug', 'custom:report')->exists())->toBeTrue();
});
