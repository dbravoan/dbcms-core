<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
});

test('the card canvas exposes accessible insertion at the start, between cards, at the end and on the empty canvas', function (): void {
    $rendered = Blade::render('<x-admin.block-manager :sections="[]" :languages="[]" />');

    // Every root zone creates an empty section at an explicit index; blocks are added inside its columns.
    expect($rendered)->toContain('addEmptySection(0)')
        ->and($rendered)->toContain('addEmptySection(index + 1)')
        ->and($rendered)->toContain('data-insert-zone="0"')
        ->and($rendered)->toContain(':data-insert-zone="index + 1"')
        ->and($rendered)->not->toContain('openAddModalAt(blocks.indexOf(selectedNode()))');

    expect($rendered)->toContain('Añadir sección al principio', 'Añadir sección aquí', 'Añadir sección al final', 'Añadir primera sección')
        ->and($rendered)->not->toContain('Añadir composición')
        ->and($rendered)->not->toMatch('/\\bpiezas?\\b/u');
});

test('block manager insertion modal shows the target column scope', function (): void {
    $rendered = Blade::render('<x-admin.block-manager :blocks="[]" :languages="[]" :currentLocale="\'es\'" />');

    expect($rendered)->toContain('columnTarget.colIndex + 1')
        ->and($rendered)->toContain('Columna')
        ->and($rendered)->toContain(__('admin.block_manager.insert_block_desc'));
});

test('block manager has zero hardcoded references to ai_assistant', function (): void {
    $content = file_get_contents(Cms::path('resources/views/components/admin/block-manager.blade.php'));
    expect($content)->not->toContain('ai_assistant')
        ->and($content)->not->toContain('route(\'admin.ai_assistant');
});
