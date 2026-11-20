<?php

declare(strict_types=1);

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Content\Contracts\PublicWidgetSemanticContributorInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Content\Pages\Models\SectionTranslation;
use DbCms\Core\Content\PublicSemanticRegistry;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublishedSectionTextService;

beforeEach(function (): void {});

test('unavailable blocks stay out of public semantic text', function (): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $section = new PageSection(['type' => 'rich_text', 'styling_json' => ['columns' => [['blocks' => [
        ['type' => 'fixture_chart', 'title' => 'Private disabled metric'],
        ['type' => 'rich_text', 'content' => 'Visible authored content'],
    ]]]]]);
    $section->setRelation('translations', collect([new SectionTranslation(['locale' => 'es', 'content_json' => []])]));
    $page = new Page;
    $page->setRelation('publishedSections', collect([$section]));
    $market = new Market(['is_active' => false]);
    $text = app(PublishedSectionTextService::class)->text($page, $market, 'es');
    expect($text)->toContain('Visible authored content')->not->toContain('Private disabled metric');
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', true);
    expect(app(PublishedSectionTextService::class)->text($page, $market, 'es'))->toContain('Private disabled metric');
});

test('an unavailable widget never calls its semantic data contributor', function (): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    $registry = app(PublicSemanticRegistry::class);
    $contributor = Mockery::mock(PublicWidgetSemanticContributorInterface::class);
    $contributor->shouldReceive('keys')->once()->andReturn(['fixture_metric']);
    $contributor->shouldNotReceive('text');
    $contributor->shouldNotReceive('ownerModule');
    $registry->registerWidget($contributor);
    expect($registry->widgetText(['type' => 'fixture_metric'], new Market(['is_active' => true]), 'es'))->toBe('');
});

test('repeatable editors derive fields from their owner definition', function (): void {
    $registry = app(BlockRegistryInterface::class);
    $schema = $registry->get('list')->getEditorSchema();
    expect($schema['items']['type'])->toBe('repeater')
        ->and($schema['items']['fields']['text']['type'])->toBe('textarea');
    $registry->validate('list', ['items' => [['text' => 'Feature', 'url' => '/details']]]);
});
