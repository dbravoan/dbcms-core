<?php

declare(strict_types=1);

use DbCms\Core\Models\OAuthServiceAccount;
use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use Laravel\Passport\Client;

beforeEach(function (): void {
    $role = Role::firstOrCreate(['slug' => 'editor'], ['name' => 'CLI editor']);
    $permission = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Access admin']);
    $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
});

it('does not create a client without an explicit role', function (): void {
    $before = Client::query()->count();
    $this->artisan('cms:create-m2m-client', ['--name' => 'Missing role'])->assertFailed();
    expect(Client::query()->count())->toBe($before);
});

it('creates a bound service account with only the chosen role and allowed scopes', function (): void {
    $role = Role::query()->where('slug', 'editor')->firstOrFail();
    $scopes = ['mcp:access', 'mcp:read', 'm2m:service'];
    $this->artisan('cms:create-m2m-client', ['--name' => 'CLI regression service', '--role' => 'editor', '--scopes' => implode(',', $scopes)])->assertSuccessful();
    $client = Client::query()->where('name', 'CLI regression service')->firstOrFail();
    $binding = OAuthServiceAccount::query()->findOrFail($client->getKey());
    $user = User::query()->findOrFail($binding->getAttribute('user_id'));
    expect($user->getAttribute('is_service_account'))->toBeTrue()
        ->and($binding->allowed_scopes)->toBe($scopes)
        ->and($user->roles->modelKeys())->toBe([$role->getKey()])
        ->and($client->getAttribute('grant_types'))->toContain('client_credentials');
});
