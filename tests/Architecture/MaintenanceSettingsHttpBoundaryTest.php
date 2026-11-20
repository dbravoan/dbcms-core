<?php

declare(strict_types=1);

use DbCms\Core\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use DbCms\Core\Maintenance\Services\MaintenanceSettingsAdministrationService;
use Tests\Support\ArchitectureBoundaryInspector;
use Tests\Support\ArchitectureSources;

test('maintenance settings decisions do not depend on HTTP response types', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach ([MaintenanceSettingsAdministrationInterface::class, MaintenanceSettingsAdministrationService::class] as $class) {
        expect($guard->dependencyViolations(ArchitectureSources::forClass($class), ['Illuminate\\Http', 'Symfony\\Component\\HttpFoundation']), $class)->toBe([]);
    }
});
