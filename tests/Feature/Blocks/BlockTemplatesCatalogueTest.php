<?php

declare(strict_types=1);

use DbCms\Core\Blocks\BlockEditorCatalogue;
use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Database\Seeders\CompositionTemplateSeeder;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
});

it('lists published plantillas in the editor catalogue for copy-on-insert', function (): void {
    $this->seed(CompositionTemplateSeeder::class);

    $data = app(BlockEditorCatalogue::class)->data();
    $templates = $data['blockTemplates'] ?? [];

    expect($templates)->not->toBeEmpty()
        ->and(collect($templates)->pluck('slug')->all())->toContain('plantilla-hero');

    $hero = collect($templates)->firstWhere('slug', 'plantilla-hero');
    expect($hero['preview_url'] ?? null)->toBeString()->toContain('/preview')
        ->and($hero['section_count'] ?? null)->toBeGreaterThan(0);

    $sections = $hero['sectionsByLocale']['es'] ?? [];
    expect($sections)->toHaveCount(2)
        ->and($sections[0]['columns_layout'] ?? null)->toBe('1-2')
        ->and($sections[0]['columns'] ?? [])->toHaveCount(2)
        ->and($sections[0]['bg_type'] ?? null)->toBe('image');

    $source = $sections[0];
    $originalId = $source['id'] ?? null;
    $nestedId = $source['columns'][0]['blocks'][0]['id'] ?? null;
    unset($source['id']);
    $clone = app(BlockTree::class)->identify($source);
    expect($clone['id'] ?? null)->not->toBeNull()
        ->and($clone['id'] ?? null)->not->toBe($originalId)
        ->and($clone['columns'][0]['blocks'][0]['id'] ?? null)->not->toBeNull()
        ->and($clone['columns'][0]['blocks'][0]['id'] ?? null)->toBe($nestedId);
});

it('marks alert payloads with nested children as unsupported', function (): void {
    $tree = app(BlockTree::class);
    expect($tree->supports(['type' => 'alert', 'content' => 'Hola', 'tone' => 'info']))->toBeTrue()
        ->and($tree->supports(['type' => 'alert', 'children' => [['type' => 'rich_text', 'content' => 'x']]]))->toBeFalse()
        ->and($tree->supports(['type' => 'countdown', 'finished_text' => 'Listo']))->toBeTrue()
        ->and($tree->supports(['type' => 'countdown', 'completion_children' => [['type' => 'rich_text', 'content' => 'x']]]))->toBeFalse()
        ->and($tree->supports(['type' => 'hotspots', 'items' => [['label' => 'A', 'x' => 10, 'y' => 10, 'text' => 'Hola']]]))->toBeTrue()
        ->and($tree->supports(['type' => 'hotspots', 'items' => [['label' => 'A', 'children' => [['type' => 'rich_text', 'content' => 'x']]]]]))->toBeFalse();
});
