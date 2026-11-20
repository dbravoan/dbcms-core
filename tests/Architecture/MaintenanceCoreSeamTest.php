<?php

declare(strict_types=1);

use DbCms\Core\Console\Commands\CmsMaintenanceCommand;
use DbCms\Core\Http\Middleware\CheckMaintenanceMode;
use DbCms\Core\Services\Admin\AdminNavigationService;
use DbCms\Core\Services\Capabilities\PlatformAdministrationActions;
use Tests\Support\ArchitectureBoundaryInspector;
use Tests\Support\ArchitectureSources;

test('core maintenance consumers depend only on the shared owner contract', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach ([PlatformAdministrationActions::class, CheckMaintenanceMode::class, CmsMaintenanceCommand::class, AdminNavigationService::class] as $class) {
        expect($guard->dependencyViolations(ArchitectureSources::forClass($class), ['DbCms\\Core\\Maintenance']), $class)->toBe([]);
    }
});
