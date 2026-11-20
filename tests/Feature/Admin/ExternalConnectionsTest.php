<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->syncWithoutDetaching([$role->id]);
    }

    public function test_admin_can_open_the_external_connections_section(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.connections.index'));

        $response->assertOk();
        $response->assertSee('Conexiones Externas y Almacenamiento');
        $response->assertSee('name="provider"', false);
        $response->assertSee('name="bucket"', false);
        $response->assertSee('name="secret_key"', false);
        $response->assertSee('externalConnections()', false);
    }

    public function test_admin_can_save_an_external_storage_connection(): void
    {
        $response = $this->actingAs($this->admin, 'web')->put(route('admin.connections.update'), [
            'provider' => 'ovh',
            'endpoint' => 'https://s3.eu-west-par.io.cloud.ovh.net',
            'region' => 'eu-west-par',
            'bucket' => 'dbcms-media',
            'access_key' => 'AKIAEXAMPLE',
            'secret_key' => 'top-secret',
            'public_url' => 'https://cdn.example.com',
            'root' => 'media',
        ]);

        $response->assertRedirect(route('admin.connections.index'));
        $response->assertSessionHas('success');

        $storage = app(ExternalStorageService::class);
        expect($storage->isConfigured())->toBeTrue();
        expect($storage->provider())->toBe('ovh');
        expect($storage->secret())->toBe('top-secret');
    }

    public function test_it_rejects_an_unknown_provider(): void
    {
        $response = $this->actingAs($this->admin, 'web')->put(route('admin.connections.update'), [
            'provider' => 'not-a-real-provider',
        ]);

        $response->assertSessionHasErrors('provider');
    }

    public function test_a_user_without_the_manage_storage_permission_is_forbidden(): void
    {
        $role = Role::firstOrCreate(['slug' => 'viewer'], ['name' => 'Viewer']);
        $access = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Administrative access']);
        $role->permissions()->syncWithoutDetaching([$access->id]);
        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->actingAs($user, 'web')
            ->get(route('admin.connections.index'))
            ->assertForbidden();
    }

    public function test_the_navigation_exposes_the_external_connections_entry_for_admins(): void
    {
        $this->actingAs($this->admin, 'web')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Conexiones externas');
    }
}
