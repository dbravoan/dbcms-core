<?php

declare(strict_types=1);

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Blocks\BlockPatch;
use DbCms\Core\Blocks\BlockStorageDisclosure;
use DbCms\Core\Blocks\BlockStyle;
use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\ContextualBlockTextInterface;
use DbCms\Core\Cms;
use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\DTOs\Content\PendingEditorialDraft;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Sections\UnavailableBlockGuard;
use DbCms\Core\Services\Capabilities\ContentSectionActions;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use DbCms\Core\Services\Content\PublishedSectionTextService;
use DbCms\Core\Services\Media\AnimationDocument;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->market = Market::query()->where('code', 'es')->firstOrFail();
    app()->instance('current.market', $this->market);
    app()->setLocale('es');
    $this->actingAs(User::query()->where('email', 'admin@db-cms.io')->firstOrFail());
});

it('renders the same nested content and typed appearance from sections and repeated Blade instances', function (): void {
    $payload = ['type' => 'accordion', 'id' => 'help', 'items' => [['id' => 'answer', 'title' => 'Ayuda', 'children' => [
        ['id' => 'title', 'type' => 'heading', 'text' => 'Respuesta libre', 'level' => 'h3'],
        ['id' => 'link', 'type' => 'button', 'label' => 'Contacto', 'url' => '/contact'],
    ]]], 'appearance' => ['base' => ['color' => '#123456'], 'mobile' => ['padding' => '1rem']]];
    $blade = Blade::render('<x-cms::block type="accordion" :payload="$payload" /><x-cms::block type="accordion" :payload="$payload" />', compact('payload'));
    $sections = app(SectionRendererService::class)->renderSection(['type' => 'accordion', 'styling_json' => $payload], $this->market, 'es');
    expect($sections)->not->toContain('cms-card', 'data-scroll-magic')->toContain('Respuesta libre', 'Contacto', 'color:#123456', 'data-cms-accordion')
        ->and(substr_count($blade, 'Respuesta libre'))->toBe(2)
        ->and($blade)->toContain('color:#123456', 'data-cms-accordion');
    preg_match_all('/\sid="([^"]+)"/', $blade, $matches);
    expect($matches[1])->not->toBeEmpty()->and(array_unique($matches[1]))->toHaveCount(count($matches[1]));
});

it('rejects bounded-tree violations and never treats undeclared data as children', function (): void {
    $tree = app(BlockTree::class);
    expect(array_column($tree->nodes(['type' => 'heading', 'text' => 'Public', 'children' => [['type' => 'heading', 'text' => 'Undeclared']]]), 'type'))->toBe(['heading']);
    $duplicate = [['type' => 'accordion', 'id' => 'outer', 'items' => [['title' => 'Panel', 'children' => [['id' => 'same', 'type' => 'heading'], ['id' => 'same', 'type' => 'heading']]]]]];
    expect(fn () => $tree->assertUniqueIds($duplicate))->toThrow(ValidationException::class);
    config(['cms.blocks.max_nodes' => 2]);
    expect(fn () => $tree->assertLimits($duplicate))->toThrow(ValidationException::class);
    config(['cms.blocks.max_nodes' => 1000, 'cms.blocks.max_payload_bytes' => 20]);
    expect(fn () => $tree->assertLimits(['type' => 'heading', 'text' => 'ññññññ']))->toThrow(ValidationException::class);
    config(['cms.blocks.max_payload_bytes' => 1048576, 'cms.blocks.max_depth' => 1]);
    expect(fn () => $tree->nodes(['type' => 'accordion', 'items' => [['title' => 'Outer', 'children' => [['type' => 'accordion', 'items' => [['title' => 'Inner', 'children' => [['type' => 'heading']]]]]]]]]))->toThrow(ValidationException::class);
});

it('updates one stable node with optimistic concurrency without replacing other locales', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'accordion', 'items' => [['title' => 'Panel', 'children' => [['type' => 'heading', 'id' => 'target', 'text' => 'Antes']]]]]],
        'en' => [['type' => 'heading', 'text' => 'Untouched English']],
    ]]);
    expect($actions->sectionsGet(['type' => 'page', 'id' => $page->id])['version'])->toBe($initial['version']);
    $result = $actions->sectionsPatch(['type' => 'page', 'id' => $page->id, 'version' => $initial['version'], 'locale' => 'es', 'operations' => [
        ['op' => 'update', 'node_id' => 'target', 'values' => ['text' => 'Después']],
    ]]);
    $selected = $actions->sectionsGet(['type' => 'page', 'id' => $page->id, 'node_id' => 'target', 'locale' => 'es']);
    expect($result)->not->toHaveKey('sections')->and($result['changed'])->toBe(1)
        ->and($result['version'])->not->toBe($initial['version'])
        ->and($selected['nodes']['es'][0]['text'])->toBe('Después')
        ->and(json_encode($actions->sectionsGet(['type' => 'page', 'id' => $page->id])))->toContain('Untouched English');
    expect(fn () => $actions->sectionsPatch(['type' => 'page', 'id' => $page->id, 'version' => $initial['version'], 'locale' => 'es', 'operations' => []]))->toThrow(ValidationException::class);
});

it('rejects cyclic moves, duplicate insertion and destructive edits of unavailable descendants', function (): void {
    $patch = app(BlockPatch::class);
    $nodes = [[
        'type' => 'accordion',
        'id' => 'parent',
        'items' => [[
            'title' => 'Parent',
            'id' => 'parent-item',
            'children' => [[
                'type' => 'accordion',
                'id' => 'child',
                'items' => [['title' => 'Child', 'children' => []]],
            ]],
        ]],
    ]];
    expect(fn () => $patch->apply($nodes, [['op' => 'move', 'node_id' => 'parent', 'parent_id' => 'child']]))->toThrow(ValidationException::class);
    expect(fn () => $patch->apply($nodes, [['op' => 'insert', 'node' => ['type' => 'heading', 'id' => 'child']]]))->toThrow(ValidationException::class);
    $legacy = ['id' => 'legacy', 'type' => 'faq', 'items' => [['question' => 'Preserve me']]];
    $nodes[0]['items'][0]['children'][] = $legacy;
    expect(fn () => $patch->apply($nodes, [['op' => 'remove', 'node_id' => 'parent']]))->toThrow(ValidationException::class);
    $updated = $patch->apply($nodes, [['op' => 'update', 'node_id' => 'parent', 'values' => ['label' => 'Conservado']]]);
    expect($updated[0]['items'][0]['children'][1])->toBe($legacy);
});

it('discovers column destinations and patches them without changing other translations or market variants', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $args = ['type' => 'page', 'id' => $page->id];
    $actions->sectionsSync($args + ['sections' => [
        'es' => [['type' => 'text', 'columns_layout' => '1-2', 'columns' => [
            ['id' => 'left', 'blocks' => [['type' => 'heading', 'id' => 'intro', 'text' => 'Introducción']]],
            ['id' => 'right', 'blocks' => []],
        ]], ['type' => 'heading', 'text' => 'Regional override']],
        'en' => [['type' => 'heading', 'text' => 'Keep English']],
    ]]);
    $regional = $page->sections()->reorder('order', 'desc')->firstOrFail();
    $regional->translations()->create(['locale' => 'es', 'market_id' => $this->market->id, 'content_json' => ['type' => 'heading', 'text' => 'Keep regional variant']]);
    $summary = $actions->sectionsGet($args + ['view' => 'summary']);
    $section = $summary['layout']['es'][0];
    expect($section['columns_layout'])->toBe('1-2')
        ->and($section['columns'][0]['blocks'])->toBe([['id' => 'intro', 'type' => 'heading', 'index' => 0]])
        ->and($section['columns'][1]['slot'])->toBe('columns.1.blocks');
    $patch = $args + ['locale' => 'es', 'version' => $summary['version'], 'operations' => [
        ['op' => 'move', 'node_id' => 'intro', 'parent_id' => $section['id'], 'slot' => $section['columns'][1]['slot']],
        ['op' => 'insert', 'parent_id' => $section['id'], 'slot' => $section['columns'][0]['slot'], 'node' => ['type' => 'heading', 'id' => 'new-intro', 'text' => 'Nueva']],
        ['op' => 'remove', 'node_id' => $regional->id],
    ]];
    $actions->sectionsPatch($patch);
    $after = $actions->sectionsGet($args + ['view' => 'summary']);
    expect(array_column($after['layout']['es'][0]['columns'][0]['blocks'], 'id'))->toBe(['new-intro'])
        ->and(array_column($after['layout']['es'][0]['columns'][1]['blocks'], 'id'))->toBe(['intro'])
        ->and($after['layout']['es'])->toHaveCount(1)
        ->and($after['layout']['en'])->toBe($summary['layout']['en'])
        ->and($regional->translations()->sole()->content_json['text'])->toBe('Keep regional variant');
    expect(fn () => $actions->sectionsPatch($patch))->toThrow(ValidationException::class);
});

it('offers compact discovery then generates the selected schema without a manual payload catalogue', function (): void {
    $catalog = app(BlockCatalogService::class);
    $summary = $catalog->describe('page', 'es', 'es', view: 'summary', includeReferences: false);
    expect($summary['blocks']['accordion'])->not->toHaveKeys(['schema', 'dynamic_data', 'appearance']);
    expect(array_column($summary['composition']['layouts'], 'id'))->toBe(['1', '2', '1-2', '2-1', '3', '4'])
        ->and($summary['composition']['layouts'][2]['proportions'])->toBe([1, 2])
        ->and($summary['templates']['tool'])->toBe('cms_block_templates')
        ->and($summary['templates']['arguments']['locale'])->toBe('es');
    expect(Artisan::call('cms:blocks:describe', ['type' => 'accordion', '--market' => 'es', '--locale' => 'es']))->toBe(0);
    $description = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($description['blocks']['accordion']['schema']['items']['fields']['children']['type'])->toBe('blocks')
        ->and($description['blocks']['accordion']['default_payload'])->toBe(app(BlockRegistryInterface::class)->get('accordion')->getDefaultPayload());
});

it('keeps semantic extraction independent of Blade and excludes opaque unavailable content', function (): void {
    $payload = ['type' => 'accordion', 'items' => [['title' => 'Pregunta', 'children' => [
        ['type' => 'quote', 'quote' => 'Contenido indexable'],
        ['type' => 'removed-owner', 'columns' => [['blocks' => [['type' => 'heading', 'text' => 'Never index this']]]]],
    ]]]];
    $text = app(PublishedSectionTextService::class)->payloadText($payload, $this->market, 'es');
    expect($text)->toContain('Pregunta', 'Contenido indexable')->not->toContain('Never index this');
});

it('routes common appearance to actual content, focused controls and selected panels', function (): void {
    $css = BlockStyle::stylesheet('#instance', ['base' => ['display' => 'grid', 'grid_template_columns' => '1fr 1fr', 'width' => '80%', 'object_fit' => 'cover'], 'focus' => ['color' => 'red'], 'selected' => ['background_color' => 'blue']], '#instance > :first-child');
    expect($css)->toContain('#instance{width:80%;}', '#instance > :first-child{display:grid;', ':focus-within', '[aria-selected="true"]', 'object-fit:cover!important;');
    expect(fn () => BlockStyle::validate(['base' => ['background_image' => 'url(https://tracker.test/a)']]))->toThrow(ValidationException::class);
});

it('only offers persistent countdown storage after explicit configuration and processing review', function (): void {
    $disclosure = app(BlockStorageDisclosure::class);
    expect($disclosure->available())->toBeFalse()->and($disclosure->categories('es'))->toBe([]);
    config(['cms.blocks.countdown_storage.enabled' => true]);
    expect($disclosure->available())->toBeFalse();
    $processing = PrivacyFixtures::processingFor([]);
    $review = $processing[0];
    $review['owner'] = 'block_countdowns';
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...$processing, $review], 'legal', 'json');
    expect($disclosure->available())->toBeTrue()->and($disclosure->categories('es')['preferences']['default'])->toBeFalse();
    config(['cms.blocks.countdown_storage.purpose' => 'necessary']);
    expect($disclosure->available())->toBeFalse();
});

it('accepts bounded animation data and rejects executable expressions and deferred resources', function (): void {
    $validator = new AnimationDocument;
    $valid = ['v' => '5.13.0', 'fr' => 30, 'ip' => 0, 'op' => 60, 'layers' => []];
    $validator->validate(json_encode($valid, JSON_THROW_ON_ERROR));
    foreach ([['x' => 'alert(1)'], ['p' => 'image.png', 'u' => 'https://remote.test/'], ['fPath' => 'https://remote.test/font.woff2']] as $resource) {
        expect(fn () => $validator->validate(json_encode($valid + ['assets' => [$resource]], JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);
    }
});

it('executes the documented Blade composition against the real registry', function (): void {
    $documentation = file_get_contents(Cms::path('docs/EXTENSIONS.md'));
    preg_match_all('/```blade\n(.*?)\n```/s', $documentation, $examples);
    $example = collect($examples[1])->first(static fn (string $source): bool => str_contains($source, 'type="accordion"'));
    expect($example)->toBeString();
    $html = Blade::render($example);
    expect($html)->toContain('Consulta nuestra página de contacto.', 'data-cms-accordion', '/contacto');
});

it('reports retired carousel payloads and preserves them without reinterpretation', function (): void {
    $old = ['id' => 'old-slider', 'type' => 'carousel', 'slides' => [['title' => 'Retained legacy data']]];
    $registry = app(BlockRegistryInterface::class);
    expect(fn () => $registry->validate('carousel', $old))->toThrow(ValidationException::class);
    $guard = app(UnavailableBlockGuard::class);
    expect($guard->reconcile($old, $old))->toBe($old)
        ->and($guard->containsUnavailable(['type' => 'accordion', 'items' => [['title' => 'Panel', 'children' => [$old]]]]))->toBeTrue()
        ->and($registry->render('carousel', $old, $this->market, 'es'))->toBe('')
        ->and(app(PublishedSectionTextService::class)->payloadText($old, $this->market, 'es'))->toBe('');
    expect(fn () => $guard->reconcile($old + ['label' => 'Unexpected edit'], $old))->toThrow(ValidationException::class);
});

it('bounds semantic expansion from owned contributors as well as authored children without cutting Unicode', function (): void {
    $block = new class extends AbstractBlockType implements ContextualBlockTextInterface
    {
        public function getType(): string
        {
            return 'large_semantic_test';
        }

        public function getName(): string
        {
            return 'Large public contributor';
        }

        public function getIcon(): string
        {
            return 'fa-solid fa-code';
        }

        public function getCategory(): string
        {
            return 'content';
        }

        public function getDefaultPayload(): array
        {
            return [];
        }

        public function render(array $payload, ?Market $market = null, ?string $locale = null): string
        {
            throw new LogicException('Semantic extraction must not execute rendering.');
        }

        public function text(array $payload, Market $market, string $locale, bool $includeWidgets = true): string
        {
            return str_repeat('ñ', 5000);
        }
    };
    app(BlockRegistryInterface::class)->register($block);
    config(['cms.blocks.max_render_bytes' => 129]);
    $payload = ['type' => 'accordion', 'items' => [['title' => 'Panel', 'children' => [['type' => 'heading', 'text' => 'Prefijo'], ['type' => 'large_semantic_test'], ['type' => 'heading', 'text' => 'After limit']]]]];
    $text = app(PublishedSectionTextService::class)->payloadText($payload, $this->market, 'es');
    expect(strlen($text))->toBeLessThanOrEqual(129)->and(mb_check_encoding($text, 'UTF-8'))->toBeTrue()
        ->and($text)->toContain('Prefijo')->not->toContain('After limit');
});

it('upgrades old Rich Text compositions atomically and can restore historical snapshots', function (): void {
    $page = CmsFixtures::home();
    $rich = ['id' => 'rich-before', 'type' => 'text', 'format' => 'markdown', 'content' => "**Público**\n"];
    $container = [
        'type' => 'text',
        'columns' => [['id' => 'column', 'blocks' => [
            ['type' => 'accordion', 'items' => [['title' => 'Descripción', 'children' => [$rich]]]],
            ['type' => 'heading', 'text' => 'Un campo text ordinario'],
        ]]],
    ];
    $section = $page->sections()->create(['type' => 'text', 'status' => 'published', 'styling_json' => $container]);
    $section->translations()->create(['locale' => 'es', 'content_json' => $container]);
    $nodePath = 'columns.0.blocks.0.items.0.children.0';
    $local = $container;
    data_set($local, $nodePath, array_replace($rich, ['format' => 'html', 'content' => '<p>Regional</p>']));
    $regional = $section->translations()->create(['locale' => 'es', 'market_id' => $this->market->id, 'content_json' => $local]);
    $flat = $page->sections()->create(['type' => 'text', 'status' => 'published']);
    $flat->translations()->create(['locale' => 'en', 'content_json' => $rich]);

    $snapshots = app(ContentSnapshotService::class);
    $before = $snapshots->capture($page->refresh());
    $workflow = Mockery::mock(EditorialWorkflowInterface::class);
    $workflow->shouldReceive('pendingDraft')->once()->andReturn(new PendingEditorialDraft(1, $before));
    app()->instance(EditorialWorkflowInterface::class, $workflow);
    $migration = require Cms::path('database/migrations/2026_11_19_000004_rename_rich_text_blocks.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'pending editorial revision');
    expect($snapshots->capture($page))->toBe($before);

    $workflow->shouldReceive('pendingDraft')->andReturn(null);
    $migration->up();
    $after = $snapshots->capture($page);
    $migration->up();
    expect($snapshots->capture($page))->toBe($after);
    expect($section->refresh()->type)->toBe('text')
        ->and(data_get($section->styling_json, $nodePath.'.type'))->toBe('rich_text')
        ->and(data_get($section->styling_json, 'columns.0.blocks.1.text'))->toBe('Un campo text ordinario')
        ->and(data_get($regional->refresh()->content_json, $nodePath.'.content'))->toBe('<p>Regional</p>')
        ->and($flat->refresh()->type)->toBe('rich_text')
        ->and($flat->translations()->sole()->content_json['content'])->toBe($rich['content']);
    $snapshots->restore($page, $before);
    expect($snapshots->hash($snapshots->capture($page)))->toBe($snapshots->hash($after));
});
