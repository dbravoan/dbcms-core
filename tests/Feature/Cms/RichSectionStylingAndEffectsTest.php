<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use DbCms\Core\Sections\SectionRendererService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RichSectionStylingAndEffectsTest extends TestCase
{
    use RefreshDatabase;

    protected SectionRendererService $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = app(SectionRendererService::class);
    }

    protected function tearDown(): void
    {
        config(['cms.theme' => 'fixture-theme']);
        parent::tearDown();
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }

    private function declarations(string $html): string
    {
        $xpath = $this->document($html);
        $styles = [];
        foreach ($xpath->query('//style | //@style') as $node) {
            $styles[] = $node->textContent;
        }

        return (string) preg_replace('/\s*:\s*/', ':', implode("\n", $styles));
    }

    public function test_renderer_renders_custom_background_color_and_borders(): void
    {
        $section = [
            'type' => 'rich_text',
            'title' => 'Sección con Estilo Personalizado',
            'content' => 'Contenido de prueba.',
            'bg_color' => '#1A202C',
            'border_top' => [
                'enabled' => true,
                'color' => '#00FF99',
                'width' => '3px',
                'style' => 'solid',
            ],
            'border_bottom' => [
                'enabled' => true,
                'color' => '#E2E8F0',
                'width' => '2px',
                'style' => 'dashed',
            ],
        ];

        $html = $this->renderer->renderSection($section);

        $this->assertStringContainsString('background-color:#1A202C', $this->declarations($html));
        $this->assertStringContainsString('border-top:3px solid #00FF99', $this->declarations($html));
        $this->assertStringContainsString('border-bottom:2px dashed #E2E8F0', $this->declarations($html));
    }

    public function test_renderer_renders_svg_shape_dividers(): void
    {
        $section = [
            'type' => 'rich_text',
            'title' => 'Sección con Separadores Decorativos',
            'content' => 'Texto con olas y corte diagonal.',
            'divider_top' => [
                'type' => 'waves',
                'color' => '#0E0E0E',
                'height' => 60,
                'flip' => false,
            ],
            'divider_bottom' => [
                'type' => 'tilt',
                'color' => '#111827',
                'height' => 80,
                'flip' => true,
            ],
        ];

        $html = $this->renderer->renderSection($section);

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('fill="#0E0E0E"', $html);
        $this->assertStringContainsString('fill="#111827"', $html);
        $this->assertStringContainsString('height:60px', $this->declarations($html));
        $this->assertStringContainsString('height:80px', $this->declarations($html));
        $this->assertSame(1, $this->document($html)->query('//*[@data-cms-presentation-divider="bottom"]//svg')->length);
    }

    public function test_renderer_renders_content_width_full_and_centered(): void
    {
        // Filling the parent is distinct from extending a row to the viewport.
        $fullWidthSection = [
            'type' => 'rich_text',
            'title' => 'Ancho Completo',
            'content' => 'Sección que ocupa el 100% de su padre.',
            'content_width' => 'full',
        ];

        $fullHtml = $this->renderer->renderSection($fullWidthSection);
        $this->assertStringContainsString('width:100%;max-width:none', $this->declarations($fullHtml));
        $this->assertStringNotContainsString('data-cms-extent="viewport"', $fullHtml);

        // 2. Centered width
        $centeredSection = [
            'type' => 'rich_text',
            'title' => 'Centrado',
            'content' => 'Sección centrada.',
            'content_width' => 'centered',
        ];

        $centeredHtml = $this->renderer->renderSection($centeredSection);
        $this->assertStringContainsString('max-width:80rem;margin-inline:auto', $this->declarations($centeredHtml));
    }

    public function test_renderer_renders_background_image_and_video_with_overlays(): void
    {
        // 1. Background Image
        $imageSection = [
            'type' => 'rich_text',
            'title' => 'Fondo con Imagen',
            'content' => 'Contenido sobre imagen.',
            'bg_type' => 'image',
            'bg_image_url' => '/hero-bg.jpg',
            'bg_image_overlay' => 'rgba(0,0,0,0.6)',
        ];

        $imageHtml = $this->renderer->renderSection($imageSection);
        $this->assertStringContainsString('background-image:url(\'/hero-bg.jpg\')', $this->declarations($imageHtml));
        $this->assertStringContainsString('background-color:rgba(0,0,0,0.6)', $this->declarations($imageHtml));

        // 2. Background Video
        $videoSection = [
            'type' => 'rich_text',
            'title' => 'Fondo con Vídeo',
            'content' => 'Contenido sobre vídeo.',
            'bg_type' => 'video',
            'bg_video_url' => '/tech-background.mp4',
            'bg_video_poster' => '/poster.jpg',
            'bg_video_overlay' => 'rgba(10,10,15,0.7)',
        ];

        $videoHtml = $this->renderer->renderSection($videoSection);
        $this->assertStringContainsString('<video', $videoHtml);
        $this->assertSame(1, $this->document($videoHtml)->query('//video[@poster="/poster.jpg"]/source[@src="/tech-background.mp4"]')->length);
        $this->assertStringContainsString('background-color:rgba(10,10,15,0.7)', $this->declarations($videoHtml));
    }

    public function test_renderer_supports_scroll_magic_video_playback_and_scroll_rotation(): void
    {
        $scrollSection = [
            'type' => 'rich_text',
            'title' => 'Sección Interactiva ScrollMagic',
            'content' => 'Animación al hacer scroll.',
            'bg_type' => 'video',
            'bg_video_url' => '/loop.mp4',
            'scroll_magic' => [
                'scrub' => 'rotate',
                'rotate_speed' => 1.5,
                'behavior' => 'video-playback',
            ],
        ];

        $html = $this->renderer->renderSection($scrollSection);

        $this->assertStringContainsString('data-scroll-scrub="rotate"', $html);
        $this->assertStringContainsString('data-scroll-video="playback"', $html);
    }
}
