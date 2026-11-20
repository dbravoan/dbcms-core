<?php

declare(strict_types=1);

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Sections\SectionRendererService;
use Illuminate\Validation\ValidationException;

test('Composer visual extensions hide without deleting their definitions', function (): void {
    $packages = app(BlockPackageManager::class);
    $registry = app(BlockRegistryInterface::class);
    expect($registry->get('fixture_chart'))->not->toBeNull();
    $packages->setEnabled('fixture_blocks', true);
    expect($registry->isAvailable('fixture_chart'))->toBeTrue();
    $packages->setEnabled('fixture_blocks', false);
    expect($registry->get('fixture_chart'))->not->toBeNull()
        ->and($registry->isAvailable('fixture_chart'))->toBeFalse()
        ->and(app(SectionRendererService::class)->renderBlockContent('fixture_chart', []))->toBe('')
        ->and(app(SectionRendererService::class)->renderSection(['type' => 'text', 'columns' => [['blocks' => [['type' => 'fixture_chart']]]]]))->toBe('');
    $packages->setEnabled('fixture_blocks', true);
    expect($registry->isAvailable('fixture_chart'))->toBeTrue();
});

test('the editor catalogue includes schemas and hides unavailable extensions', function (): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $registry = app(BlockRegistryInterface::class);
    expect($registry->get('heading')->getEditorSchema())->toHaveKey('text')
        ->and($registry->getActiveBlocks())->not->toHaveKey('fixture_chart')
        ->and($registry->all())->toHaveKey('fixture_chart');
});

test('native primitives remain available when every external package is disabled', function (): void {
    $packages = app(BlockPackageManager::class);
    $packages->setEnabled('fixture_blocks', false);
    $registry = app(BlockRegistryInterface::class);
    expect($packages->all())->not->toHaveKeys(['basic', 'charts'])
        ->and($registry->isAvailable('rich_text'))->toBeTrue()
        ->and($registry->isAvailable('charts'))->toBeTrue()
        ->and($registry->render('heading', ['text' => 'Native heading']))->toContain('Native heading');
    $registry->validate('rich_text', ['content' => 'New']);
});

test('block validation checks repeater item shapes and field values before rendering', function (): void {
    $registry = app(BlockRegistryInterface::class);

    $registry->validate('charts', ['items' => [['label' => 'Revenue', 'value' => 42]]]);
    expect(fn () => $registry->validate('charts', ['items' => [['value' => 'invalid']]]))->toThrow(ValidationException::class)
        ->and(fn () => $registry->validate('charts', ['items' => ['invalid']]))->toThrow(ValidationException::class);
});
