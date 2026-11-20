<?php

declare(strict_types=1);

use DbCms\Core\Maintenance\Services\MaintenanceService;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\File;
use Tests\Support\MaintenanceComposition;

uses(MaintenanceComposition::class);

test('a role without backoffice access does not bypass public maintenance', function (): void {
    $previousStorage = app()->storagePath();
    $temporaryStorage = sys_get_temp_dir().'/dbcms-maintenance-auth-'.bin2hex(random_bytes(8));
    File::makeDirectory($temporaryStorage.'/framework', 0700, true);
    app()->useStoragePath($temporaryStorage);
    app()->forgetInstance(MaintenanceService::class);
    app(MaintenanceService::class)->enable();
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['slug' => 'custom-no-access'], ['name' => 'No backoffice access']));
    try {
        $this->actingAs($user)->get('/')->assertStatus(503);
    } finally {
        app(MaintenanceService::class)->disable();
        app()->useStoragePath($previousStorage);
        File::deleteDirectory($temporaryStorage);
    }
});
