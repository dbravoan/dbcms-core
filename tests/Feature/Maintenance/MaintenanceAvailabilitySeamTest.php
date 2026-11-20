<?php

declare(strict_types=1);

use DbCms\Core\Maintenance\Services\MaintenanceService;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Support\MaintenanceComposition;

uses(MaintenanceComposition::class);

beforeEach(function (): void {
    $this->maintenanceStorage = sys_get_temp_dir().'/dbcms-maintenance-test-'.bin2hex(random_bytes(8));
    $this->originalStorage = app()->storagePath();
    File::makeDirectory($this->maintenanceStorage.'/framework', 0700, true);
    app()->useStoragePath($this->maintenanceStorage);
    app()->forgetInstance(MaintenanceService::class);
});

afterEach(function (): void {
    app()->useStoragePath($this->originalStorage);
    File::deleteDirectory($this->maintenanceStorage);
});

test('maintenance works without an installable module or database setting', function (): void {
    expect(app(ModuleManager::class)->discoverModules())->not->toHaveKey('maintenance');
    $maintenance = app(MaintenanceService::class);
    expect($maintenance->isAvailable())->toBeTrue();
    $maintenance->enable();
    expect($maintenance->isActive())->toBeTrue();
    $maintenance->disable();
    expect($maintenance->isActive())->toBeFalse();
});

test('a fresh core-only process loads maintenance and generic actions', function (): void {
    $program = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $mode = $app->make(DbCms\Core\Modules\Contracts\MaintenanceModeInterface::class);
    $actions = $app->make(DbCms\Core\Services\Capabilities\PlatformAdministrationActions::class);
    echo json_encode(['available' => $mode->isAvailable(), 'generic_actions' => $actions instanceof DbCms\Core\Services\Capabilities\PlatformAdministrationActions, 'provider_loaded' => $app->getProvider(DbCms\Core\Maintenance\MaintenanceServiceProvider::class) !== null], JSON_THROW_ON_ERROR);
    PHP;
    $process = new Process([PHP_BINARY, '-r', $program], base_path(), ['CMS_MODULES' => '', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:']);
    $process->mustRun();
    expect(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR))->toBe(['available' => true, 'generic_actions' => true, 'provider_loaded' => true]);
});
