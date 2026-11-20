<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use Tests\Support\ArchitectureBoundaryInspector;
use Tests\Support\ArchitectureSources;

test('installer HTTP adapters delegate persistence and cryptography', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach (ArchitectureSources::directory(Cms::path('src/Http/Controllers/Install')) as $path => $source) {
        expect($guard->dependencyViolations($source, ['PDO', 'DbCms\\Core\\Models\\User', 'DbCms\\Core\\Models\\Role', 'Illuminate\\Support\\Facades\\DB', 'Illuminate\\Support\\Facades\\File', 'Illuminate\\Support\\Facades\\Artisan', 'Illuminate\\Support\\Facades\\Hash']), $path)->toBe([]);
    }
});
