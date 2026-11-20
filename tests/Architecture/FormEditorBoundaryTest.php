<?php

declare(strict_types=1);

use DbCms\Core\Forms\Http\Controllers\Admin\FormCreateGetController;
use DbCms\Core\Forms\Http\Controllers\Admin\FormEditGetController;
use Tests\Support\ArchitectureBoundaryInspector;
use Tests\Support\ArchitectureSources;

test('form editor transports delegate presentation and domain state', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach ([FormCreateGetController::class, FormEditGetController::class] as $class) {
        expect($guard->dependencyViolations(ArchitectureSources::forClass($class), ['DbCms\\Core\\Models\\Language', 'DbCms\\Core\\Models\\Market', 'DbCms\\Core\\Forms\\Models\\FormField', 'DbCms\\Core\\Services\\Content\\EditorialDraftService', 'DbCms\\Core\\Forms\\Services\\FormSchemaService', 'DbCms\\Core\\Forms\\Support\\FieldTypeOptions']), $class)->toBe([]);
    }
});
