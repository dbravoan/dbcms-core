<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use DbCms\Core\Jobs\MigrateMediaToExternalJob;
use DbCms\Core\Models\MediaMigrationRun;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalConnectionsMigrationTest extends TestCase
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

    public function test_admin_can_start_a_migration_run(): void
    {
        $this->configureExternalStorage();
        Queue::fake();

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.connections.migrate'), ['rewrite_urls' => 1]);

        $response->assertRedirect(route('admin.connections.index'));
        $response->assertSessionHas('success');

        $run = MediaMigrationRun::query()->latest('id')->first();
        expect($run)->not->toBeNull();
        expect($run->status)->toBe(MediaMigrationRun::STATUS_PENDING);
        expect($run->user_id)->toBe($this->admin->id);

        Queue::assertPushed(MigrateMediaToExternalJob::class);
    }

    public function test_it_blocks_a_second_concurrent_run(): void
    {
        $this->configureExternalStorage();
        Queue::fake();

        MediaMigrationRun::create(['status' => MediaMigrationRun::STATUS_RUNNING]);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.connections.migrate'));

        $response->assertRedirect(route('admin.connections.index'));
        $response->assertSessionHas('error');
        Queue::assertNotPushed(MigrateMediaToExternalJob::class);
    }

    public function test_it_refuses_to_start_without_external_storage(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.connections.migrate'));

        $response->assertSessionHas('error');
        Queue::assertNotPushed(MigrateMediaToExternalJob::class);
    }

    public function test_the_status_endpoint_returns_progress(): void
    {
        $run = MediaMigrationRun::create([
            'status' => MediaMigrationRun::STATUS_RUNNING,
            'total' => 10,
            'processed' => 5,
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson(route('admin.connections.migration.status'));

        $response->assertOk();
        $response->assertJsonPath('run.id', $run->id);
        $response->assertJsonPath('run.percent', 50);
        $response->assertJsonPath('run.finished', false);
    }

    public function test_the_connections_page_exposes_the_migration_card(): void
    {
        $this->configureExternalStorage();

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.connections.index'));

        $response->assertOk();
        $response->assertSee('Migrar archivos existentes');
    }

    private function configureExternalStorage(): void
    {
        app(ExternalStorageService::class)->save([
            'provider' => 's3',
            'region' => 'eu-west-1',
            'bucket' => 'test-bucket',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret',
            'root' => 'media',
        ]);

        Storage::fake('external');
    }
}
