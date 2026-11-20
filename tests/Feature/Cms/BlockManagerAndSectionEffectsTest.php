<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Content\Pages\Models\SectionTranslation;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('block manager renders rich sections with ScrollMagic and Animate.css effects', function (): void {
    $renderer = app(SectionRendererService::class);
    $market = Market::where('is_default', true)->first() ?? Market::first();

    $page = Page::create([
        'template' => 'default',
        'is_published' => true,
        'published_at' => now(),
    ]);

    $heroSettings = [
        'type' => 'heading',
        'text' => 'Plataforma Next-Gen dbCMS',
        'level' => 'h1',
        'animation' => 'fadeInUp',
        'animation_delay' => '0.2s',
        'container_style' => 'neon-glow',
    ];

    $sectionHero = PageSection::create([
        'page_id' => $page->id,
        'type' => 'heading',
        'order' => 1,
        'is_active' => true,
        'styling_json' => $heroSettings,
    ]);

    SectionTranslation::create([
        'page_section_id' => $sectionHero->id,
        'locale' => 'es',
        'content_json' => $heroSettings,
    ]);

    $featuresSettings = [
        'type' => 'list',
        'items' => [
            ['text' => 'Velocidad Extrema: caché optimizada en milisegundos.'],
            ['text' => 'Multi-Mercado: rutas localizadas automáticas.'],
        ],
        'animation' => 'zoomIn',
        'animation_delay' => '0.4s',
        'container_style' => 'cyber-card',
    ];

    $sectionFeatures = PageSection::create([
        'page_id' => $page->id,
        'type' => 'list',
        'order' => 2,
        'is_active' => true,
        'styling_json' => $featuresSettings,
    ]);

    SectionTranslation::create([
        'page_section_id' => $sectionFeatures->id,
        'locale' => 'es',
        'content_json' => $featuresSettings,
    ]);

    // Render sections
    $heroHtml = $renderer->renderSection($sectionHero, $market, 'es');
    $featuresHtml = $renderer->renderSection($sectionFeatures, $market, 'es');

    expect($heroHtml)
        ->toContain('Plataforma Next-Gen dbCMS')
        ->toContain('data-scroll-magic')
        ->toContain('data-scroll-effect="fadeInUp"')
        ->toContain('data-scroll-delay="0.2s"');

    expect($featuresHtml)
        ->toContain('Velocidad Extrema: caché optimizada en milisegundos.')
        ->toContain('Multi-Mercado: rutas localizadas automáticas.')
        ->toContain('data-scroll-effect="zoomIn"')
        ->toContain('data-scroll-delay="0.4s"');
});

test('admin can store and update pages with dynamic sections json', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $sections = [
        [
            'id' => 'sec_1',
            'type' => 'heading',
            'text' => 'Título de prueba',
            'level' => 'h1',
            'animation' => 'flipInX',
            'animation_delay' => '0.3s',
        ],
        [
            'id' => 'sec_2',
            'type' => 'accordion',
            'label' => 'FAQ Test',
            'items' => [
                ['title' => '¿Es fácil de usar?', 'children' => [['type' => 'rich_text', 'content' => 'Sí, completamente modular.']]],
            ],
            'animation' => 'fadeInLeft',
            'animation_delay' => '0.5s',
        ],
    ];

    $response = $this->actingAs($admin, 'web')->post(route('admin.pages.store'), [
        'translations' => ['es' => ['title' => 'Página Modular con Secciones', 'slug' => 'pagina-modular-secciones']],
        'is_published' => 1,
        'sections_json' => json_encode(['es' => $sections], JSON_THROW_ON_ERROR),
    ]);

    $response->assertRedirect(route('admin.pages.index'));

    $page = Page::whereHas('translations', fn ($q) => $q->where('slug', 'pagina-modular-secciones'))->first();
    expect($page)->not->toBeNull();
    expect($page->sections)->toHaveCount(2);

    $accordionSection = $page->sections->where('type', 'accordion')->first();
    expect($accordionSection)->not->toBeNull();
    expect($accordionSection->styling_json['animation'])->toBe('fadeInLeft');
});

test('multi-column sections render responsive CSS grid with column sub-blocks', function (): void {
    $renderer = app(SectionRendererService::class);
    $market = Market::where('is_default', true)->first() ?? Market::first();

    $multiColSection = [
        'id' => 'sec_multi_1',
        'type' => 'text',
        'columns_layout' => '2',
        'columns' => [
            [
                'id' => 'col_1',
                'blocks' => [
                    [
                        'type' => 'rich_text',
                        'title' => 'Columna Izquierda',
                        'content' => 'Información técnica del módulo.',
                    ],
                ],
            ],
            [
                'id' => 'col_2',
                'blocks' => [
                    [
                        'type' => 'rich_text',
                        'title' => 'Columna Derecha',
                        'content' => 'Formulario de conversión rápido.',
                    ],
                    [
                        'type' => 'button',
                        'label' => 'Contactar ahora',
                        'url' => '/contacto',
                    ],
                ],
            ],
        ],
        'animation' => 'fadeInUp',
        'container_style' => 'cyber-card',
    ];

    $html = $renderer->renderSection($multiColSection, $market, 'es');

    expect($html)->toContain('cms-section-grid');
    expect($html)->toContain('grid-cols-1 md:grid-cols-2');
    expect($html)->toContain('Columna Izquierda');
    expect($html)->toContain('Información técnica del módulo.');
    expect($html)->toContain('Columna Derecha');
    expect($html)->toContain('Contactar ahora');
});

test('asymmetric multi-column section renders 1-2 layout with col-span classes', function (): void {
    $renderer = app(SectionRendererService::class);
    $market = Market::where('is_default', true)->first() ?? Market::first();

    $asymmetricSection = [
        'id' => 'sec_multi_asym',
        'type' => 'text',
        'columns_layout' => '1-2',
        'columns' => [
            [
                'id' => 'col_1',
                'blocks' => [
                    ['type' => 'rich_text', 'title' => 'Menú Lateral', 'content' => 'Navegación 33%'],
                ],
            ],
            [
                'id' => 'col_2',
                'blocks' => [
                    ['type' => 'rich_text', 'title' => 'Contenido Central', 'content' => 'Espacio 66%'],
                ],
            ],
        ],
    ];

    $html = $renderer->renderSection($asymmetricSection, $market, 'es');

    expect($html)->toContain('grid-cols-1 md:grid-cols-3');
    expect($html)->toContain('md:col-span-2');
    expect($html)->toContain('Menú Lateral');
    expect($html)->toContain('Contenido Central');
});
