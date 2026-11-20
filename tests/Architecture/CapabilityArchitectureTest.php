<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Capabilities\Resources\CapabilityResource;
use DbCms\Core\Capabilities\Resources\CapabilityResourceRegistry;
use DbCms\Core\Cms;
use DbCms\Core\Contracts\Services\ExecutionServiceInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Providers\CmsCapabilitiesServiceProvider;
use DbCms\Core\Services\Capabilities\PlatformResourceCatalog;
use DbCms\Core\Services\Orchestration\ExecutionService;
use Illuminate\Container\Container;
use Tests\Support\ArchitectureBoundaryInspector;
use Tests\Support\ArchitectureSources;

test('capability execution has no MCP or HTTP transport dependencies', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach (['Capabilities', 'Services/Capabilities', 'Services/Orchestration'] as $directory) {
        foreach (ArchitectureSources::directory(Cms::path('src/'.$directory)) as $path => $source) {
            $forbidden = ['DbCms\Core\Mcp'];
            if ($directory === 'Services/Orchestration') {
                $forbidden = [...$forbidden, 'Illuminate\Http\Request', 'DbCms\Core\Http'];
            }
            expect($guard->dependencyViolations($source, $forbidden), $path)->toBe([]);
        }
    }
    expect((new ReflectionClass(ExecutionService::class))->implementsInterface(ExecutionServiceInterface::class))->toBeTrue();
});

test('orchestration controllers delegate persistence and business decisions', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach (ArchitectureSources::directory(Cms::path('src/Http/Controllers/Admin/Orchestration')) as $path => $source) {
        expect($guard->controllerViolations($source), $path)->toBe([]);
    }
});

test('shared content mutation controllers delegate to their owning services', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach (['Pages' => ['Page']] as $module => $entities) {
        foreach ($entities as $entity) {
            foreach (['Post', 'Put', 'Delete'] as $verb) {
                $namespace = $module === 'Pages' ? 'DbCms\Core\Content\Pages' : 'DbCms\Modules\\'.$module;
                $class = $namespace.'\\Http\\Controllers\\Admin\\'.$entity.$verb.'Controller';
                expect($guard->controllerViolations(ArchitectureSources::forClass($class)), $class)->toBe([]);
            }
        }
    }
});

test('core capability provider owns its shared registry without MCP adapters', function (): void {
    expect((new ArchitectureBoundaryInspector)->dependencyViolations(ArchitectureSources::forClass(CmsCapabilitiesServiceProvider::class), ['DbCms\Core\Mcp']))->toBe([]);
    $container = new Container;
    (new CmsCapabilitiesServiceProvider($container))->register();
    expect($container->bound(CapabilityRegistry::class))->toBeTrue();
    $core = $container->make(CapabilityResourceRegistry::class);
    $core->registerMany(PlatformResourceCatalog::all());
    $additional = new CapabilityResource('test_shared_registry', 'Test shared registry', Market::class, 'manage:markets');
    $core->register($additional);
    expect($core->get('test_shared_registry'))->toBe($additional);
});
