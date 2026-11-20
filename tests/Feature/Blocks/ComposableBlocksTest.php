<?php

declare(strict_types=1);

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Blocks\BlockRenderer;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Types\DrawerBlock;
use DbCms\Core\Blocks\Types\HeadingBlock;
use DbCms\Core\Blocks\Types\ListBlock;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\Market;
use Illuminate\Validation\ValidationException;
use Tests\Support\PrivacyFixtures;

test('the basic package exposes independent composable primitives and retires rigid collections', function (): void {
    $registry = app(BlockRegistryInterface::class);
    foreach (['heading', 'image', 'button', 'icon', 'list', 'separator', 'spacer', 'quote', 'rating', 'price', 'accordion', 'tabs', 'carousel', 'alert', 'drawer', 'counter', 'countdown', 'progress', 'hotspots', 'lottie', 'map', 'playlist'] as $type) {
        expect($registry->isAvailable($type))->toBeTrue($type);
        $registry->validate($type, $registry->get($type)->getDefaultPayload());
    }
    expect($registry->all())->not->toHaveKeys(['faq', 'pricing', 'testimonials', 'container'])
        ->and($registry->get('carousel')->getEditorSchema())->toHaveKey('items')->not->toHaveKey('slides');
});

test('composable slots use the shared blocks schema at every declared location', function (): void {
    $registry = app(BlockRegistryInterface::class);
    expect($registry->get('drawer')->getEditorSchema()['children']['type'])->toBe('blocks')
        ->and($registry->get('alert')->getEditorSchema())->toHaveKey('content')->not->toHaveKey('children')
        ->and($registry->get('countdown')->getEditorSchema())->toHaveKey('finished_text')->not->toHaveKey('completion_children')
        ->and($registry->get('hotspots')->getEditorSchema()['items']['fields'])->toHaveKey('text')->not->toHaveKey('children');
    foreach (['accordion', 'tabs', 'carousel'] as $type) {
        expect($registry->get($type)->getEditorSchema()['items']['fields']['children']['type'])->toBe('blocks');
    }
    expect($registry->get('lottie')->getEditorSchema()['animation_url']['media_kind'])->toBe('animation')
        ->and($registry->get('playlist')->getEditorSchema()['items']['fields']['media_url']['media_kind'])->toBe('audio')
        ->and($registry->get('playlist')->getEditorSchema()['items']['fields']['video_url']['media_kind'])->toBe('video');
});

test('containers and interactive panels render nested blocks through the public renderer', function (string $type): void {
    $children = [['type' => 'heading', 'text' => '<Contenido anidado>', 'level' => 'h3'], ['type' => 'button', 'label' => 'Contacto', 'url' => '/contact']];
    $payload = $type === 'drawer'
        ? ['children' => $children]
        : ['items' => [['title' => 'Panel', 'label' => 'Punto', 'x' => 25, 'y' => 50, 'children' => $children]]];
    $html = app(BlockRenderer::class)->render($type, $payload);
    expect($html)->toContain('&lt;Contenido anidado&gt;', 'Contacto')->not->toContain('<Contenido anidado>');
})->with(['accordion', 'tabs', 'carousel', 'drawer']);

test('leaf interactive blocks render text without nested children', function (): void {
    $registry = app(BlockRegistryInterface::class);
    expect($registry->render('alert', ['content' => 'Aviso **importante**', 'tone' => 'warning']))->toContain('cms-alert', 'importante')
        ->and($registry->render('hotspots', ['items' => [['label' => 'Punto', 'x' => 25, 'y' => 50, 'text' => 'Detalle del punto']]]))->toContain('Detalle del punto')
        ->and($registry->render('countdown', ['mode' => 'visit', 'duration' => 1, 'finished_text' => 'Terminado']))->toContain('Terminado')->not->toContain('data-countdown-completion');
});

test('package availability applies to nested primitives as well as their containers', function (): void {
    app(BlockPackageManager::class)->setEnabled('fixture_blocks', false);
    expect(app(BlockRenderer::class)->children([['type' => 'fixture_chart', 'title' => 'Hidden']]))->not->toContain('Hidden');
});

test('composable validation enforces numeric and explicit timezone constraints', function (string $type, array $payload): void {
    app(BlockRegistryInterface::class)->validate($type, $payload);
})->with([
    ['hotspots', ['items' => [['x' => -1, 'y' => 50]]]],
    ['counter', ['decimals' => 7]],
    ['countdown', ['mode' => 'absolute', 'deadline' => '2030-01-01T12:00:00']],
    ['lottie', ['speed' => 8]],
    ['heading', ['level' => 'script']],
])->throws(ValidationException::class);

test('public primitives preserve readable fallbacks and safe media playback defaults', function (): void {
    $registry = app(BlockRegistryInterface::class);
    $counter = $registry->render('counter', ['value' => 42.5, 'decimals' => 1, 'label' => 'Metric']);
    $tabs = $registry->render('tabs', ['items' => [['title' => 'Panel', 'children' => [['type' => 'heading', 'text' => 'Readable']]]]]);
    $playlist = $registry->render('playlist', ['items' => [['title' => 'Track', 'media_url' => '/storage/track.mp3']]]);
    expect($counter)->toContain('42.5', 'Metric')
        ->and($tabs)->toContain('Readable', 'data-tab-fallback-title')
        ->and($playlist)->toContain('controls', 'preload="none"', '/storage/track.mp3')->not->toContain('autoplay')
        ->and($registry->get('carousel')->getDefaultPayload()['autoplay'])->toBeFalse()
        ->and($registry->get('lottie')->getDefaultPayload()['autoplay'])->toBeFalse()
        ->and($registry->get('countdown')->getDefaultPayload()['persistence'])->toBe('memory');
});

test('stored unsafe primitive URLs are removed and coordinates cannot inject CSS', function (): void {
    $registry = app(BlockRegistryInterface::class);
    expect($registry->render('button', ['label' => 'Unsafe', 'url' => 'javascript:alert(1)']))->not->toContain('javascript:')
        ->and($registry->render('hotspots', ['items' => [['x' => '0; background:url(https://tracker.test/pixel)', 'y' => 120, 'label' => 'Point', 'text' => 'Safe']]]))->not->toContain('tracker.test')->toContain('top: 100%');
});

test('map providers and remote playlist media stay inert behind shared consent', function (): void {
    $processing = PrivacyFixtures::processingFor([]);
    $serviceProcessing = $processing[0];
    $serviceProcessing['owner'] = 'external_composable';
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...$processing, $serviceProcessing], 'legal', 'json');
    config(['privacy.external_services' => ['composable' => ['provider' => 'Example provider', 'purpose' => 'Maps and requested media', 'origins' => ['https://provider.example.test']]]]);
    $registry = app(BlockRegistryInterface::class);
    foreach ([
        ['map', ['title' => 'Office', 'embed_url' => 'https://provider.example.test/map', 'external_service' => 'composable']],
        ['playlist', ['external_service' => 'composable', 'items' => [['title' => 'Remote', 'media_url' => 'https://provider.example.test/track.mp3']]]],
    ] as [$type, $payload]) {
        $html = $registry->render($type, $payload);
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        expect($xpath->query('//iframe[not(ancestor::template)] | //audio[not(ancestor::template)] | //video[not(ancestor::template)]')->length)->toBe(0)
            ->and($html)->toContain('data-cms-consent="external"', '<template>');
    }
    expect($registry->render('map', ['embed_url' => 'https://unconfigured.example.test/map', 'external_service' => 'composable']))->not->toContain('<iframe')
        ->and($registry->render('playlist', ['items' => [['media_url' => 'https://unconfigured.example.test/track.mp3']]]))->not->toContain('<audio');
});

test('owner presentation never executes children outside its declared structural slots', function (): void {
    $calls = (object) ['renders' => 0];
    app(BlockRegistryInterface::class)->register(new class($calls) extends AbstractBlockType
    {
        public function __construct(private readonly stdClass $calls) {}

        public function getType(): string
        {
            return 'structural-sentinel';
        }

        public function getName(): string
        {
            return 'Sentinel';
        }

        public function getIcon(): string
        {
            return '';
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
            $this->calls->renders++;

            return 'Hidden child executed';
        }
    });
    $children = [['type' => 'structural-sentinel']];
    app(HeadingBlock::class)->render(['text' => 'Visible', 'children' => $children, 'items' => [['children' => $children]]]);
    app(ListBlock::class)->render(['children' => $children, 'items' => [['text' => 'Visible', 'children' => $children]]]);
    expect($calls->renders)->toBe(0);
});

test('visitor countdown persistence requires project configuration reviewed processing and the configured purpose', function (): void {
    $registry = app(BlockRegistryInterface::class);
    $payload = ['mode' => 'visit', 'duration' => 90, 'persistence' => 'local', 'storage_purpose' => 'preferences'];
    expect(fn () => $registry->validate('countdown', $payload))->toThrow(ValidationException::class);
    config(['cms.blocks.countdown_storage' => ['enabled' => true, 'purpose' => 'preferences', 'max_days' => 2]]);
    expect(fn () => $registry->validate('countdown', $payload))->toThrow(ValidationException::class);
    $processing = PrivacyFixtures::processingFor([]);
    $storageProcessing = $processing[0];
    $storageProcessing['owner'] = 'block_countdowns';
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...$processing, $storageProcessing], 'legal', 'json');
    $registry->validate('countdown', $payload);
    expect($registry->get('countdown')->getEditorSchema()['persistence']['options'])->toHaveKeys(['memory', 'session', 'local'])
        ->and(fn () => $registry->validate('countdown', array_replace($payload, ['storage_purpose' => 'marketing'])))->toThrow(ValidationException::class);
});

test('repeated authored drawer aliases retain unique runtime targets', function (): void {
    $block = app(DrawerBlock::class);
    $payload = ['overlay_id' => 'shared-panel', 'title' => 'Shared title', 'trigger_label' => 'Open', 'children' => []];
    $html = $block->render($payload).$block->render($payload);
    preg_match_all('/data-overlay-id="([^"]+)"/', $html, $matches);
    expect($matches[1])->toHaveCount(2)->and($matches[1][0])->not->toBe($matches[1][1])->and(substr_count($html, 'data-overlay-alias="shared-panel"'))->toBe(2);
});

test('countdown finished text and media playlists retain safe content and one native player', function (): void {
    $registry = app(BlockRegistryInterface::class);
    $countdown = $registry->render('countdown', ['mode' => 'visit', 'duration' => 1, 'finished_text' => 'Finished composition']);
    $playlist = $registry->render('playlist', ['kind' => 'video', 'items' => [
        ['title' => 'First', 'video_url' => '/media/one.mp4', 'poster_url' => '/media/one.webp', 'subtitle_url' => '/media/es.vtt', 'subtitle_language' => 'es', 'subtitle_label' => 'Español'],
        ['title' => 'Second', 'video_url' => '/media/two.mp4'],
    ]]);
    expect($registry->get('countdown')->getEditorSchema())->toHaveKey('finished_text')->not->toHaveKey('completion_children')
        ->and($countdown)->toContain('Finished composition')->not->toContain('data-countdown-completion')
        ->and(substr_count($playlist, '<video'))->toBe(1)
        ->and($playlist)->toContain('data-playlist-select="1"', 'data-playlist-next', 'poster="/media/one.webp"', 'src="/media/es.vtt"', 'srclang="es"')->not->toContain('autoplay');
});
