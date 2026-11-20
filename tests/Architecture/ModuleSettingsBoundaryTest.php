<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use Tests\Support\ArchitectureBoundaryInspector;
use Tests\Support\ArchitectureSources;

test('core settings HTTP adapters have no storage or module implementation knowledge', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach (ArchitectureSources::directory(Cms::path('src/Http/Controllers/Admin/ModuleSettings')) as $path => $source) {
        expect($guard->dependencyViolations($source, ['DbCms\\Core\\Models\\CmsModule', 'Illuminate\\Support\\Facades\\DB', 'DbCms\\Modules']), $path)->toBe([]);
    }
});
