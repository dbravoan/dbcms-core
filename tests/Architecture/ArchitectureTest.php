<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\ParserFactory;
use Tests\Support\ArchitectureBoundaryInspector;
use Tests\Support\ArchitectureSources;

test('core and generated integration enforce strict types', function (): void {
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    foreach ([...ArchitectureSources::directory(Cms::path('stubs/application')), ...ArchitectureSources::directory(Cms::path('src'))] as $path => $source) {
        $declaration = ($parser->parse($source) ?? [])[0] ?? null;
        expect($declaration, $path)->toBeInstanceOf(Declare_::class);
        expect($declaration->declares[0]->key->name, $path)->toBe('strict_types');
        expect($declaration->declares[0]->value->value, $path)->toBe(1);
    }
});

test('dependency guard resolves imported aliases and explicit container class names', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    expect($guard->dependencyViolations('<?php use DbCms\Modules\Example\Bridge as Remote; new Remote;', ['DbCms\Modules\Example']))->not->toBeEmpty();
    expect($guard->dependencyViolations('<?php app("DbCms\\\\Modules\\\\Example\\\\Bridge");', ['DbCms\Modules\Example']))->not->toBeEmpty();
    expect($guard->dependencyViolations('<?php use DbCms\Core\Capabilities\CapabilityRegistry; new CapabilityRegistry;', ['DbCms\Modules\Example']))->toBe([]);
});

test('core HTTP controllers do not depend on optional implementations', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    foreach (ArchitectureSources::directory(Cms::path('src/Http/Controllers')) as $path => $source) {
        expect($guard->dependencyViolations($source, ['DbCms\Modules']), $path)->toBe([]);
    }
});

test('owned models cannot directly use network clients', function (): void {
    $guard = new ArchitectureBoundaryInspector;
    $paths = [Cms::path('src/Models'), Cms::path('src/Content/Pages/Models'), Cms::path('src/Forms/Models')];
    expect($guard->dependencyViolations('<?php use Illuminate\Support\Facades\Http as Remote; class Model { function save() { Remote::post("outside"); }}', ['Illuminate\Support\Facades\Http']))->not->toBeEmpty();
    foreach ($paths as $directory) {
        foreach (ArchitectureSources::directory($directory, allowEmpty: true) as $path => $source) {
            expect($guard->dependencyViolations($source, ['Illuminate\Support\Facades\Http', 'GuzzleHttp']), $path)->toBe([]);
        }
    }
});
