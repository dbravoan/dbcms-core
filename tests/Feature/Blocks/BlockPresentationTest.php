<?php

declare(strict_types=1);

use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Blocks\BlockEditorCatalogue;
use DbCms\Core\Blocks\BlockPresentation;
use DbCms\Core\Blocks\BlockRenderer;
use DbCms\Core\Blocks\BlockStyle;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Sections\SectionAppearance;
use DbCms\Core\Sections\SectionRendererService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    $this->market = Market::where('code', 'es')->firstOrFail();
    app()->instance('current.market', $this->market);
    app()->setLocale('es');
});

it('accepts alpha colors in gradient controls while rejecting embedded resource functions', function (): void {
    BlockStyle::validate(['base' => ['background_image' => 'linear-gradient(45deg, rgb(0 0 0 / 0.5) 0%, #fff 100%)']]);
    expect(fn () => BlockStyle::validate(['base' => ['background_image' => 'linear-gradient(red, url(/private.png))']]))->toThrow(ValidationException::class);
});

it('leaves new block and section surfaces free of implicit cards and motion', function (): void {
    $payload = ['type' => 'heading', 'text' => 'Theme defaults'];
    $block = app(BlockRenderer::class)->render('heading', $payload, $this->market, 'es');
    $section = app(SectionRendererService::class)->renderSection($payload, $this->market, 'es');
    foreach ([$block, $section] as $html) {
        expect($html)->toContain('data-cms-presentation', 'data-cms-extent="theme"', 'Theme defaults')
            ->not->toContain('cms-card', 'data-scroll-magic', 'my-8', 'p-6');
    }
});

it('uses the same explicit legacy and appearance cascade for sections nested blocks and Blade', function (): void {
    $payload = ['id' => 'painted', 'type' => 'heading', 'text' => 'Painted', 'bg_color' => '#111111', 'padding' => '2rem', 'animation' => 'bounceIn',
        'appearance' => ['extent' => 'viewport', 'base' => ['background_color' => '#222222', 'padding' => '1rem', 'width' => '80%']]];
    $block = app(BlockRenderer::class)->render('heading', $payload, $this->market, 'es');
    $section = app(SectionRendererService::class)->renderSection($payload, $this->market, 'es');
    $blade = Blade::render('<x-cms::block type="heading" :payload="$payload" />', compact('payload'));
    foreach ([$block, $section, $blade] as $html) {
        expect(strpos($html, 'background-color:#111111'))->toBeLessThan(strpos($html, 'background-color:#222222'))
            ->and($html)->toContain('padding:1rem', 'data-cms-extent="viewport"', 'data-scroll-effect="bounceIn"')
            ->not->toContain('100vw');
    }
    $nested = app(BlockRenderer::class)->render('accordion', ['items' => [['title' => 'Panel', 'children' => [$payload]]]], $this->market, 'es');
    expect($nested)->toContain('data-cms-extent="viewport"', 'background-color:#222222');
});

it('renders column appearance and root ownership markers with separate outer and inner widths', function (): void {
    $payload = ['type' => 'drawer', 'id' => 'root', 'title' => 'Panel', 'content_width' => 'custom', 'content_max_width' => '52rem', 'appearance' => ['extent' => 'viewport', 'base' => ['width' => '90%']], 'columns' => [
        ['id' => 'column', 'style' => ['bg_color' => '#111111', 'animation' => 'fadeIn', 'appearance' => ['base' => ['background_color' => '#abcdef', 'display' => 'grid']]], 'blocks' => [['id' => 'child', 'type' => 'heading', 'text' => 'Column', 'animation' => 'fadeInUp']]],
    ]];
    $html = app(SectionRendererService::class)->renderSection($payload, $this->market, 'es');
    expect($html)->toContain('data-cms-node="root"', 'data-cms-column-index="0"', 'data-cms-column-owner="root"', 'max-width:52rem', 'width:90%', 'background-color:#abcdef', 'display:grid', 'data-scroll-effect="fadeInUp"');
    expect(substr_count($html, 'width:90%'))->toBe(1);
    // Columns never own motion attributes even if style carries animation keys.
    expect(preg_match('/data-cms-presentation="column"[^>]*data-scroll-/', $html))->toBe(0);
});

it('limits media fitting and scroll effects to explicit owned layers', function (): void {
    $html = app(BlockPresentation::class)->render('<figure><img src="/child.jpg"></figure>', ['bg_type' => 'image', 'bg_image_url' => '/background.jpg', 'bg_behavior' => 'parallax', 'bg_position' => 'top right', 'bg_size' => 'contain', 'bg_repeat' => 'repeat-x', 'appearance' => ['base' => ['object_fit' => 'contain']]], id: 'owned');
    expect($html)->toContain('data-cms-presentation-media', 'data-cms-presentation-motion', 'data-scroll-scrub="parallax"', 'background-position:top right', 'background-size:contain', 'background-repeat:repeat-x', '#owned > [data-cms-presentation-media] :is(img,video)')
        ->not->toContain('#owned :is(img,video)');
});

it('targets foreground media through the section frame as well as the direct Blade frame', function (): void {
    $payload = ['type' => 'image', 'image_url' => '/image.jpg', 'appearance' => ['base' => ['object_fit' => 'contain']]];
    $section = app(SectionRendererService::class)->renderSection($payload, $this->market, 'es');
    $blade = Blade::render('<x-cms::block type="image" :payload="$payload" />', compact('payload'));
    expect($section)->toContain('> [data-cms-presentation="block"] > [data-cms-presentation-content] > .cms-image > img', 'object-fit:contain!important')
        ->and($blade)->toContain('> [data-cms-presentation-content] > .cms-image > img', 'object-fit:contain!important');
});

it('blocks undeclared remote backgrounds and gates reviewed optional video and poster together', function (): void {
    $renderer = app(BlockPresentation::class);
    expect($renderer->render('Content', ['bg_type' => 'video', 'bg_video_url' => 'https://unreviewed.test/track.mp4']))->not->toContain('unreviewed.test', '<video');
    config(['privacy.external_services.media' => ['provider' => 'Reviewed media', 'purpose' => 'Background video', 'origins' => ['https://media.test']]]);
    $entry = PrivacyFixtures::processingFor([])[0];
    $entry['owner'] = 'external_media';
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [$entry], 'legal', 'json');
    $html = $renderer->render('Content', ['bg_type' => 'video', 'bg_external_service' => 'media', 'bg_video_url' => 'https://media.test/video.mp4', 'bg_video_poster' => 'https://media.test/poster.jpg']);
    expect($html)->toContain('data-cms-consent="external"', '<template>', 'data-cms-presentation-video', 'preload="none"')
        ->not->toContain(' autoplay');
    expect($renderer->render('Content', ['bg_type' => 'video', 'bg_external_service' => 'media', 'bg_video_url' => 'https://media.test/video.mp4', 'bg_video_poster' => 'https://unreviewed.test/poster.jpg']))->not->toContain('<video');
});

it('publishes typed presentation metadata and rejects unsafe style media and width values', function (): void {
    expect(BlockStyle::properties()['padding'])->toHaveKeys(['group', 'control', 'units', 'supports_theme_variables'])
        ->and(SectionAppearance::properties())->toHaveKeys(['content_max_width', 'bg_position', 'bg_repeat', 'bg_video_fit', 'bg_external_service']);
    foreach ([['content_max_width' => 'calc(100vw + url(x))'], ['bg_position' => 'url(https://tracker.test)'], ['appearance' => ['extent' => 'screen']], ['appearance' => ['base' => ['background_image' => 'url(https://tracker.test)']]], ['appearance' => ['base' => ['background_image' => 'linear-gradient(url(local),red)']]]] as $payload) {
        expect(fn () => SectionAppearance::validate($payload))->toThrow(ValidationException::class);
    }
});

it('shares one theme variable map between catalog and editor without per-field copies', function (): void {
    $catalog = app(BlockCatalogService::class)->describe('page', 'es', 'es', blockType: 'accordion', view: 'detail', includeReferences: false);
    $editor = app(BlockEditorCatalogue::class)->data();
    expect($catalog['appearance']['theme_variables'])->toBe(BlockStyle::themeVariables())
        ->and($editor['blockAppearanceVariables'])->toBe($catalog['appearance']['theme_variables'])
        ->and($editor['blockAppearanceProperties'])->toBe($catalog['appearance']['properties'])
        ->and($editor['blockPresentationProperties'])->toBe($catalog['appearance']['presentation_properties'])
        ->and($editor['blockAppearanceProperties']['padding']['supports_theme_variables'])->toBeTrue()
        ->and($editor['blockAppearanceProperties']['display']['supports_theme_variables'])->toBeFalse()
        ->and($editor['blockPresentationProperties']['content_max_width']['supports_theme_variables'])->toBeTrue();
    foreach ([$editor['blockAppearanceProperties'], $editor['blockPresentationProperties']] as $properties) {
        foreach ($properties as $descriptor) {
            expect($descriptor)->not->toHaveKey('theme_variables');
        }
    }
});

it('describes visual gradient alignment and shadow controls without replacing saved custom CSS', function (): void {
    $properties = BlockStyle::properties();
    $presentation = SectionAppearance::properties();
    expect($properties['background_image'])->toMatchArray(['control' => 'gradient', 'group' => 'background', 'gradient_types' => ['linear' => 'Lineal', 'radial' => 'Radial']])
        ->and($properties['background_image']['gradient_defaults']['angle'])->toBe(135)
        ->and($properties['justify_content']['label'])->toBe('Distribuir contenido')
        ->and($properties['justify_content']['control'])->toBe('alignment')
        ->and($properties['text_align']['options']['left'])->toBe('Izquierda')
        ->and($properties['align_items']['options']['stretch'])->toBe('Estirar')
        ->and($presentation['bg_type']['options']['gradient'])->toBe('Degradado')
        ->and($presentation['bg_video_fit']['options']['cover'])->toBe('Cubrir')
        ->and($presentation['animation']['options']['flipInX'])->toBe('Girar al aparecer')
        ->and($presentation)->not->toHaveKey('scroll_magic.scrub');
    foreach (['linear-gradient(23deg,rgba(12,34,56,0.42),#abcdef)', 'radial-gradient(circle at center,#123456 0%,#abcdef 100%)'] as $gradient) {
        $payload = ['bg_type' => 'gradient', 'appearance' => ['base' => ['background_image' => $gradient]]];
        SectionAppearance::validate($payload);
        expect(SectionAppearance::sanitize($payload))->toBe($payload)
            ->and(app(BlockPresentation::class)->render('Content', $payload))->toContain('background-image:'.$gradient);
    }
    foreach (['box_shadow', 'text_shadow'] as $key) {
        expect($properties[$key]['control'])->toBe('select')
            ->and(array_values($properties[$key]['options']))->toBe(['Sin sombra', 'Suave', 'Media', 'Intensa']);
        foreach (array_keys($properties[$key]['options']) as $value) {
            BlockStyle::validate(['base' => [$key => $value]]);
        }
        $custom = '2px 3px 7px rgba(12,34,56,0.42)';
        expect(BlockStyle::stylesheet('#shadow', ['base' => [$key => $custom]]))->toContain(str_replace('_', '-', $key).':'.$custom);
    }
});

it('inherits cleared legacy border fields and keeps authored placement separate from entrance motion', function (): void {
    $legacy = app(BlockPresentation::class)->render('Content', ['border_top' => ['enabled' => true, 'color' => '#123456']]);
    expect($legacy)->toContain('border-top:1px solid #123456');
    $html = app(BlockPresentation::class)->render('Content', ['animation' => 'flipInX', 'appearance' => ['base' => ['border_top_color' => '#123456', 'border_top_style' => 'solid', 'transform' => 'rotate(12deg)', 'opacity' => '.4']]], id: 'motion');
    expect($html)->toContain('border-top-color:#123456', 'border-top-style:solid', 'transform:rotate(12deg)', 'opacity:.4', 'data-cms-presentation-entrance')
        ->not->toContain('border-top-width:1px');
});

it('targets a drawer panel when the stored row alone declares its type', function (): void {
    $page = CmsFixtures::home();
    $section = $page->sections()->create(['type' => 'drawer', 'order' => 1, 'status' => 'published', 'is_active' => true, 'styling_json' => ['appearance' => ['base' => ['width' => '70%', 'background_color' => '#123456']]]]);
    $section->translations()->create(['locale' => 'es', 'content_json' => ['title' => 'Panel']]);
    $html = app(SectionRendererService::class)->renderSection($section, $this->market, 'es');
    expect($html)->toContain('.cms-drawer-panel{width:70%;background-color:#123456;}');
});

it('mounts drawer media and motion inside its owned panel without painting the trigger or backdrop', function (): void {
    $html = app(BlockRenderer::class)->render('drawer', ['title' => 'Panel', 'animation' => 'flipInX', 'bg_type' => 'image', 'bg_image_url' => '/panel.jpg', 'appearance' => ['base' => ['width' => '70%']]], $this->market, 'es');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $layers = $xpath->query('//*[@data-cms-presentation-media]');
    expect($layers)->toHaveCount(1)
        ->and($layers->item(0)->parentNode->attributes->getNamedItem('class')->nodeValue)->toContain('cms-drawer-panel')
        ->and($layers->item(0)->parentNode->attributes->getNamedItem('data-scroll-effect')->nodeValue)->toBe('flipInX')
        ->and($html)->toContain('.cms-drawer-panel{width:70%;}');
    expect($xpath->query('//*[@data-cms-presentation="block"]/@data-scroll-effect'))->toHaveCount(0);
});

it('emits appearance.html_class on section and block surfaces without changing design presets', function (): void {
    $payload = [
        'id' => 'hero',
        'type' => 'heading',
        'text' => 'Hero',
        'appearance' => [
            'html_class' => ['folio-hero', 'intent'],
            'classes' => ['stack'],
            'base' => ['gap' => '2rem'],
        ],
    ];
    $block = app(BlockRenderer::class)->render('heading', $payload, $this->market, 'es');
    $section = app(SectionRendererService::class)->renderSection($payload, $this->market, 'es');
    foreach ([$block, $section] as $html) {
        expect($html)->toMatch('/class="[^"]*\bfolio-hero\b/')
            ->toMatch('/class="[^"]*\bintent\b/')
            ->toContain('display:flex', 'flex-direction:column', 'gap:2rem')
            ->not->toMatch('/class="[^"]*\bstack\b/');
    }
});

it('rejects hostile or unknown html_class tokens and respects an optional theme allowlist', function (): void {
    expect(fn () => BlockStyle::validate(['html_class' => ['Folio Hero']]))->toThrow(ValidationException::class)
        ->and(fn () => BlockStyle::validate(['html_class' => ['folio-hero" onclick="alert(1)']]))->toThrow(ValidationException::class)
        ->and(fn () => BlockStyle::validate(['html_class' => array_map(static fn (int $i): string => 'token'.$i, range(1, 20))]))->toThrow(ValidationException::class);

    BlockStyle::validate(['html_class' => ['folio-hero', 'intent']]);
    expect(BlockStyle::htmlClassTokens(['html_class' => ['folio-hero', 'intent', 'folio-hero']]))->toBe(['folio-hero', 'intent']);

    config(['cms.blocks.html_classes' => ['folio-hero' => 'Hero folio', 'intent' => 'Intent home']]);
    expect(BlockStyle::htmlClassOptions())->toBe(['folio-hero' => 'Hero folio', 'intent' => 'Intent home'])
        ->and(fn () => BlockStyle::validate(['html_class' => ['unknown-skin']]))->toThrow(ValidationException::class);
    BlockStyle::validate(['html_class' => ['folio-hero']]);

    $catalog = app(BlockCatalogService::class)->describe('page', 'es', 'es', blockType: 'heading', view: 'detail', includeReferences: false);
    $editor = app(BlockEditorCatalogue::class)->data();
    expect($catalog['appearance']['html_class']['allowlist'])->toBe(['folio-hero' => 'Hero folio', 'intent' => 'Intent home'])
        ->and($catalog['appearance']['html_class']['allowlist_required'])->toBeTrue()
        ->and($catalog['appearance']['classes'])->toContain('stack', 'row', 'card')
        ->and($editor['blockHtmlClasses'])->toBe(['folio-hero' => 'Hero folio', 'intent' => 'Intent home']);
});
