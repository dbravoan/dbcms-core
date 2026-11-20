<?php

declare(strict_types=1);

namespace DbCms\Core\Database\Seeders;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\BlockComponentSection;
use DbCms\Core\Models\BlockComponentSectionTranslation;
use DbCms\Core\Models\BlockComponentTranslation;
use DbCms\Core\Models\Market;
use Illuminate\Database\Seeder;

/**
 * Published composition templates loaded on every install.
 *
 * Templates live in block_components* (admin Plantillas). They are copied into
 * pages on insert — not public ejemplo-* pages. Idempotent by Spanish slug.
 * Every template is seeded in Spanish and English (CompositionTemplateEnglish).
 *
 * Each template is a real section composition: columns_layout + columns[].blocks,
 * section/column presentation (fondo, overlay, motion, divisores, appearance)
 * and nested generic blocks — never a flat single-column dump of every piece.
 */
final class CompositionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->retireLegacyExamplePages();

        $marketIds = Market::query()->where('is_active', true)->pluck('id')->all();

        foreach ($this->templates() as $definition) {
            $slug = $definition['slug'];
            $template = BlockComponent::query()->whereHas(
                'translations',
                static fn ($query) => $query->where('locale', 'es')->where('slug', $slug)
            )->first();

            if ($template === null) {
                $template = BlockComponent::query()->create([
                    'is_active' => true,
                    'is_published' => true,
                    'published_at' => now(),
                    'exposed_properties' => [],
                ]);
            } else {
                $template->fill([
                    'is_active' => true,
                    'is_published' => true,
                    'published_at' => $template->published_at ?? now(),
                    'exposed_properties' => [],
                ])->save();
                foreach ($template->sections as $section) {
                    $section->translations()->delete();
                    $section->delete();
                }
            }

            $template->markets()->sync($marketIds);

            BlockComponentTranslation::query()->updateOrCreate(
                ['block_component_id' => $template->id, 'locale' => 'es', 'market_id' => null],
                [
                    'title' => $definition['title'],
                    'slug' => $slug,
                ]
            );
            BlockComponentTranslation::query()->updateOrCreate(
                ['block_component_id' => $template->id, 'locale' => 'en', 'market_id' => null],
                CompositionTemplateEnglish::TEMPLATES[$slug] ?? ['title' => $definition['title'], 'slug' => $slug.'-en'],
            );

            foreach ($definition['sections'] as $order => $section) {
                $layout = (string) ($section['content']['columns_layout'] ?? '1');
                $styling = array_replace(
                    $section['styling'] ?? [],
                    ['columns_layout' => $layout],
                );

                $row = BlockComponentSection::query()->create([
                    'block_component_id' => $template->id,
                    'order' => $order + 1,
                    'type' => $section['type'],
                    'styling_json' => $styling,
                    'is_active' => true,
                    'status' => 'published',
                    'published_at' => now(),
                ]);

                $payload = app(BlockTree::class)->identify(['type' => $section['type']] + $section['content']);
                unset($payload['id']);

                $contents = ['es' => $payload, 'en' => CompositionTemplateEnglish::translate($payload)];
                foreach ($contents as $locale => $content) {
                    BlockComponentSectionTranslation::query()->create([
                        'block_component_section_id' => $row->id,
                        'locale' => $locale,
                        'market_id' => null,
                        'content_json' => $content,
                    ]);
                }
            }
        }
    }

    /** Remove public ejemplo-* / plantilla-* pages from earlier installs that used pages as templates. */
    private function retireLegacyExamplePages(): void
    {
        $legacy = [
            'ejemplo-hero', 'ejemplo-cta', 'ejemplo-caracteristicas', 'ejemplo-faq',
            'ejemplo-planes', 'ejemplo-testimonio', 'ejemplo-galeria',
            ...array_column($this->templates(), 'slug'),
        ];
        $pages = Page::query()->whereHas(
            'translations',
            static fn ($query) => $query->where('locale', 'es')->whereIn('slug', $legacy)
        )->get();

        foreach ($pages as $page) {
            foreach ($page->sections as $section) {
                $section->translations()->delete();
                $section->delete();
            }
            $page->translations()->delete();
            $page->markets()->detach();
            $page->delete();
        }
    }

    /** @return list<array{slug: string, title: string, description: string, sections: list<array<string, mixed>>}> */
    private function templates(): array
    {
        return [
            [
                'slug' => 'plantilla-hero',
                'title' => 'Hero',
                'description' => 'Apertura a pantalla con fondo, overlay, columnas asimétricas y franja de cifras.',
                'sections' => [
                    $this->section(
                        [
                            'bg_type' => 'image',
                            'bg_image_url' => '/images/placeholder.svg',
                            'bg_image_overlay' => 'rgba(0,0,0,0.58)',
                            'bg_behavior' => 'parallax',
                            'bg_size' => 'cover',
                            'bg_position' => 'center',
                            'content_width' => 'full',
                            'container_style' => 'transparent',
                            'animation' => 'fadeInUp',
                            'animation_delay' => '0.05s',
                            'animation_duration' => '1s',
                            'padding' => '5rem 1.5rem',
                            'divider_bottom' => ['type' => 'waves', 'color' => 'var(--cms-surface)', 'height' => 72, 'flip' => false],
                            'scroll_magic' => ['behavior' => 'fadeInUp', 'delay' => '0.05s', 'duration' => '1s'],
                            'appearance' => [
                                'extent' => 'viewport',
                                'base' => [
                                    'min_height' => '78vh',
                                    'align_items' => 'center',
                                ],
                            ],
                        ],
                        '1-2',
                        [
                            $this->column([
                                ['type' => 'alert', 'tone' => 'info', 'dismissible' => false, 'label' => 'Novedad', 'content' => 'Nuevo: auditoría SEO gratuita para las webs que publiquemos este trimestre.'],
                                ['type' => 'heading', 'text' => 'Webs rápidas que convierten visitas en clientes', 'level' => 'h1', 'appearance' => ['base' => ['font_size' => '2.75rem', 'line_height' => '1.15', 'color' => '#ffffff']]],
                                ['type' => 'rich_text', 'content' => 'Diseñamos, redactamos y publicamos tu web en seis semanas. Tú te centras en tu negocio; nosotros, en que te encuentren.', 'format' => 'markdown', 'appearance' => ['base' => ['color' => '#f4f4f5', 'max_width' => '36rem']]],
                                ['type' => 'spacer', 'height' => 12],
                                ['type' => 'button', 'label' => 'Pedir presupuesto', 'url' => '/contacto', 'variant' => 'primary', 'action' => 'link'],
                                ['type' => 'button', 'label' => 'Ver planes', 'url' => '/contacto', 'variant' => 'secondary', 'action' => 'link'],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'justify_content' => 'center', 'gap' => '0.85rem', 'padding' => '1rem 0']],
                            ]),
                            $this->column([
                                ['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Equipo revisando el diseño de una web en un portátil', 'fit' => 'cover', 'appearance' => ['base' => ['border_radius' => '1rem', 'min_height' => '22rem', 'box_shadow' => '0 24px 60px rgba(0,0,0,0.35)']]],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'align_items' => 'center', 'justify_content' => 'center']],
                            ]),
                        ],
                    ),
                    $this->section(
                        [
                            'bg_type' => 'color',
                            'bg_color' => 'var(--cms-surface-2)',
                            'content_width' => 'full',
                            'container_style' => 'transparent',
                            'animation' => 'fadeInUp',
                            'animation_delay' => '0.1s',
                            'padding' => '2.5rem 1.5rem',
                        ],
                        '4',
                        [
                            $this->column([['type' => 'counter', 'value' => 120, 'suffix' => '+', 'label' => 'Clientes']]),
                            $this->column([['type' => 'counter', 'value' => 48, 'suffix' => ' h', 'label' => 'Soporte / semana']]),
                            $this->column([['type' => 'counter', 'value' => 99, 'suffix' => '%', 'label' => 'Disponibilidad']]),
                            $this->column([['type' => 'counter', 'value' => 12, 'suffix' => '', 'label' => 'Mercados']]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-cta',
                'title' => 'Llamada a la acción',
                'description' => 'Banda con fondo, texto centrado y dos botones.',
                'sections' => [
                    $this->section(
                        [
                            'bg_type' => 'color',
                            'bg_color' => '#0b1220',
                            'content_width' => 'narrow',
                            'container_style' => 'cyber-glass',
                            'animation' => 'zoomIn',
                            'animation_delay' => '0.05s',
                            'animation_duration' => '0.9s',
                            'padding' => '3.5rem 1.5rem',
                            'divider_top' => ['type' => 'tilt', 'color' => 'var(--cms-surface)', 'height' => 48, 'flip' => false],
                            'divider_bottom' => ['type' => 'tilt', 'color' => 'var(--cms-surface)', 'height' => 48, 'flip' => true],
                            'appearance' => ['base' => ['text_align' => 'center']],
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => '¿Hablamos de tu próximo proyecto?', 'level' => 'h2', 'appearance' => ['base' => ['color' => '#ffffff']]],
                                ['type' => 'rich_text', 'content' => 'Cuéntanos qué necesitas y en 48 horas te enviamos una propuesta con plazos y presupuesto cerrados.', 'format' => 'markdown', 'appearance' => ['base' => ['color' => '#d4d4d8']]],
                                ['type' => 'spacer', 'height' => 8],
                                ['type' => 'button', 'label' => 'Pedir propuesta', 'url' => '/contacto', 'variant' => 'primary', 'action' => 'link'],
                                ['type' => 'button', 'label' => 'Hablar con ventas', 'url' => '/contacto', 'variant' => 'secondary', 'action' => 'link'],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'align_items' => 'center', 'gap' => '0.75rem']],
                            ]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-caracteristicas',
                'title' => 'Características',
                'description' => 'Introducción más tres columnas con icono, título y texto.',
                'sections' => [
                    $this->section(
                        [
                            'content_width' => 'centered',
                            'animation' => 'fadeInDown',
                            'padding' => '3rem 1.5rem 1rem',
                            'appearance' => ['base' => ['text_align' => 'center']],
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Todo lo que necesita tu web para crecer', 'level' => 'h2'],
                                ['type' => 'rich_text', 'content' => 'Un único equipo para el diseño, el contenido y la tecnología, con resultados que puedes medir.', 'format' => 'markdown'],
                            ]),
                        ],
                    ),
                    $this->section(
                        [
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'animation_delay' => '0.08s',
                            'padding' => '1rem 1.5rem 3rem',
                        ],
                        '3',
                        [
                            $this->featureColumn('fa-solid fa-pen-ruler', 'Diseño a medida', 'Interfaces claras y accesibles, adaptadas a móvil, tableta y escritorio.'),
                            $this->featureColumn('fa-solid fa-magnifying-glass-chart', 'Contenido que posiciona', 'Textos revisados por especialistas en SEO y escritos para tu cliente ideal.'),
                            $this->featureColumn('fa-solid fa-headset', 'Soporte cercano', 'Respuesta en menos de 24 horas laborables y mejoras continuas cada mes.'),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-faq',
                'title' => 'Preguntas frecuentes',
                'description' => 'Columna de contexto y acordeón con respuestas.',
                'sections' => [
                    $this->section(
                        [
                            'container_style' => 'cyber-card',
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'padding' => '3rem 1.5rem',
                        ],
                        '1-2',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Preguntas frecuentes', 'level' => 'h2'],
                                ['type' => 'rich_text', 'content' => 'Resolvemos las dudas más habituales antes de empezar. Si no encuentras la tuya, escríbenos.', 'format' => 'markdown'],
                                ['type' => 'button', 'label' => 'Contactar soporte', 'url' => '/contacto', 'variant' => 'secondary', 'action' => 'link'],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'gap' => '0.75rem', 'padding_right' => '1rem']],
                            ]),
                            $this->column([
                                ['type' => 'accordion', 'items' => [
                                    ['title' => '¿Cuánto tarda en estar lista mi web?', 'children' => [['type' => 'rich_text', 'content' => 'Una web corporativa de hasta diez páginas suele publicarse en cuatro a seis semanas.', 'format' => 'markdown']]],
                                    ['title' => '¿Podré editar los contenidos yo mismo?', 'children' => [['type' => 'rich_text', 'content' => 'Sí. Te entregamos un panel sencillo y una sesión de formación para tu equipo.', 'format' => 'markdown']]],
                                    ['title' => '¿Qué incluye el mantenimiento?', 'children' => [['type' => 'rich_text', 'content' => 'Actualizaciones de seguridad, copia de seguridad diaria y soporte por correo en horario laboral.', 'format' => 'markdown']]],
                                    ['title' => '¿Trabajáis con empresas de fuera de España?', 'children' => [['type' => 'rich_text', 'content' => 'Sí. Publicamos webs en varios idiomas y mercados desde el mismo panel.', 'format' => 'markdown']]],
                                ]],
                            ]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-planes',
                'title' => 'Comparativa de planes',
                'description' => 'Introducción y tres columnas de precio con la central destacada.',
                'sections' => [
                    $this->section(
                        [
                            'content_width' => 'centered',
                            'animation' => 'fadeInDown',
                            'padding' => '3rem 1.5rem 1rem',
                            'appearance' => ['base' => ['text_align' => 'center']],
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Elige el plan adecuado', 'level' => 'h2'],
                                ['type' => 'rich_text', 'content' => 'Precios claros, sin permanencia y con todo lo necesario para empezar a vender en internet.', 'format' => 'markdown'],
                            ]),
                        ],
                    ),
                    $this->section(
                        [
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'animation_delay' => '0.1s',
                            'padding' => '1rem 1.5rem 3.5rem',
                        ],
                        '3',
                        [
                            $this->planColumn('Starter', '29€', 'al mes', ['1 mercado', '2 idiomas', 'Hasta 10 páginas'], 'Elegir Starter', false),
                            $this->planColumn('Professional', '99€', 'al mes', ['8 mercados', 'Módulos ilimitados', 'Soporte prioritario'], 'Elegir Professional', true),
                            $this->planColumn('Enterprise', 'A medida', '', ['SLA', 'SSO', 'Onboarding'], 'Contactar', false),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-testimonio',
                'title' => 'Testimonio',
                'description' => 'Cita y valoración junto a retrato y rol.',
                'sections' => [
                    $this->section(
                        [
                            'container_style' => 'cyber-glass',
                            'bg_type' => 'color',
                            'bg_color' => 'var(--cms-surface-2)',
                            'content_width' => 'full',
                            'animation' => 'fadeInLeft',
                            'padding' => '3.5rem 1.5rem',
                        ],
                        '2',
                        [
                            $this->column([
                                ['type' => 'rating', 'value' => 5, 'maximum' => 5],
                                ['type' => 'quote', 'quote' => 'Pasamos de semanas a minutos en los despliegues internacionales.', 'author' => 'Elena Martínez'],
                                ['type' => 'rich_text', 'content' => 'Lanzamos la web en cinco países en un mes, y el equipo de marketing ya publica sin depender de desarrollo.', 'format' => 'markdown'],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'gap' => '1rem', 'justify_content' => 'center']],
                            ]),
                            $this->column([
                                ['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Elena Martínez', 'fit' => 'cover', 'appearance' => ['base' => ['border_radius' => '9999px', 'width' => '10rem', 'height' => '10rem', 'object_fit' => 'cover']]],
                                ['type' => 'heading', 'text' => 'Elena Martínez', 'level' => 'h3'],
                                ['type' => 'rich_text', 'content' => '**CTO · TechNexus**', 'format' => 'markdown'],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'align_items' => 'center', 'text_align' => 'center', 'gap' => '0.5rem']],
                            ]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-galeria',
                'title' => 'Galería de imágenes',
                'description' => 'Título y tres columnas de imagen con pie.',
                'sections' => [
                    $this->section(
                        [
                            'content_width' => 'centered',
                            'animation' => 'fadeIn',
                            'padding' => '3rem 1.5rem 1rem',
                            'appearance' => ['base' => ['text_align' => 'center']],
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Proyectos recientes', 'level' => 'h2'],
                                ['type' => 'rich_text', 'content' => 'Una muestra de los espacios, productos y equipos con los que hemos trabajado este año.', 'format' => 'markdown'],
                            ]),
                        ],
                    ),
                    $this->section(
                        [
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'padding' => '1rem 1.5rem 3rem',
                        ],
                        '3',
                        [
                            $this->galleryColumn('Oficina luminosa con zona de trabajo compartida', 'Espacio de trabajo'),
                            $this->galleryColumn('Producto artesanal sobre una mesa de madera', 'Detalle de producto'),
                            $this->galleryColumn('Equipo reunido revisando un proyecto', 'Equipo en contexto'),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-estadisticas',
                'title' => 'Estadísticas',
                'description' => 'Cabecera y cuatro contadores en columnas.',
                'sections' => [
                    $this->section(
                        [
                            'bg_type' => 'image',
                            'bg_image_url' => '/images/placeholder.svg',
                            'bg_image_overlay' => 'rgba(8,12,24,0.72)',
                            'bg_behavior' => 'fixed',
                            'bg_size' => 'cover',
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'padding' => '4rem 1.5rem',
                            'appearance' => ['extent' => 'viewport', 'base' => ['text_align' => 'center']],
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Resultados que importan', 'level' => 'h2', 'appearance' => ['base' => ['color' => '#ffffff']]],
                                ['type' => 'rich_text', 'content' => 'Más de diez años ayudando a empresas a crecer en internet.', 'format' => 'markdown', 'appearance' => ['base' => ['color' => '#e4e4e7']]],
                            ]),
                        ],
                    ),
                    $this->section(
                        [
                            'bg_type' => 'color',
                            'bg_color' => '#0b1220',
                            'content_width' => 'full',
                            'animation' => 'zoomIn',
                            'animation_delay' => '0.08s',
                            'padding' => '2.5rem 1.5rem 3.5rem',
                        ],
                        '4',
                        [
                            $this->column([['type' => 'counter', 'value' => 120, 'suffix' => '+', 'label' => 'Clientes']], ['appearance' => ['base' => ['text_align' => 'center', 'color' => '#ffffff']]]),
                            $this->column([['type' => 'counter', 'value' => 48, 'suffix' => ' h', 'label' => 'Soporte / semana']], ['appearance' => ['base' => ['text_align' => 'center', 'color' => '#ffffff']]]),
                            $this->column([['type' => 'counter', 'value' => 99, 'suffix' => '%', 'label' => 'Disponibilidad']], ['appearance' => ['base' => ['text_align' => 'center', 'color' => '#ffffff']]]),
                            $this->column([['type' => 'counter', 'value' => 12, 'suffix' => '', 'label' => 'Mercados']], ['appearance' => ['base' => ['text_align' => 'center', 'color' => '#ffffff']]]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-contacto',
                'title' => 'Contacto con mapa',
                'description' => 'Datos de contacto en una columna y mapa en la otra.',
                'sections' => [
                    $this->section(
                        [
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'padding' => '3.5rem 1.5rem',
                            'divider_bottom' => ['type' => 'curve', 'color' => 'var(--cms-surface)', 'height' => 56, 'flip' => false],
                        ],
                        '2',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Hablemos', 'level' => 'h2'],
                                ['type' => 'rich_text', 'content' => 'Cuéntanos tu proyecto. Respuesta en un día laborable.', 'format' => 'markdown'],
                                ['type' => 'list', 'items' => [
                                    ['text' => 'hola@ejemplo.com'],
                                    ['text' => '+34 900 000 000'],
                                    ['text' => 'Lunes a viernes · 9:00–18:00'],
                                ]],
                                ['type' => 'spacer', 'height' => 8],
                                ['type' => 'button', 'label' => 'Escribir', 'url' => 'mailto:hola@ejemplo.com', 'variant' => 'primary', 'action' => 'link'],
                                ['type' => 'button', 'label' => 'Cómo llegar', 'url' => 'https://www.openstreetmap.org/search?query=Calle%20de%20Alcal%C3%A1%2048%2C%20Madrid', 'variant' => 'secondary', 'action' => 'link', 'new_window' => true],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'gap' => '0.75rem']],
                            ]),
                            $this->column([
                                ['type' => 'map', 'title' => 'Oficina de Madrid', 'address' => 'Calle de Alcalá 48, 2.º izquierda, 28014 Madrid', 'embed_url' => '', 'directions_url' => 'https://www.openstreetmap.org/search?query=Calle%20de%20Alcal%C3%A1%2048%2C%20Madrid'],
                            ], [
                                'appearance' => ['base' => ['min_height' => '18rem', 'border_radius' => '0.75rem', 'overflow' => 'hidden']],
                            ]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-video',
                'title' => 'Vídeo destacado',
                'description' => 'Texto a la izquierda y reproductor a la derecha.',
                'sections' => [
                    $this->section(
                        [
                            'bg_type' => 'color',
                            'bg_color' => '#09090b',
                            'content_width' => 'full',
                            'animation' => 'fadeIn',
                            'padding' => '4rem 1.5rem',
                            'appearance' => ['extent' => 'viewport', 'base' => ['align_items' => 'center']],
                        ],
                        '2',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Míralo en acción', 'level' => 'h2', 'appearance' => ['base' => ['color' => '#ffffff']]],
                                ['type' => 'rich_text', 'content' => 'En dos minutos te enseñamos a editar textos, cambiar imágenes y publicar una página nueva sin ayuda técnica.', 'format' => 'markdown', 'appearance' => ['base' => ['color' => '#d4d4d8']]],
                                ['type' => 'button', 'label' => 'Solicitar demo', 'url' => '/contacto', 'variant' => 'primary', 'action' => 'link'],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'justify_content' => 'center', 'gap' => '0.85rem']],
                            ]),
                            $this->column([
                                ['type' => 'video', 'video_url' => '', 'poster_url' => '/images/placeholder.svg', 'title' => 'Demostración del panel', 'caption' => 'Recorrido de dos minutos por el panel de edición.', 'controls' => true],
                            ]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-pestanas',
                'title' => 'Pestañas de contenido',
                'description' => 'Introducción y pestañas con texto, imagen y botón.',
                'sections' => [
                    $this->section(
                        [
                            'container_style' => 'cyber-card',
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'padding' => '3rem 1.5rem',
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Cómo trabajamos', 'level' => 'h2'],
                                ['type' => 'rich_text', 'content' => 'Un método probado en más de 300 proyectos, de la primera reunión al lanzamiento.', 'format' => 'markdown'],
                                ['type' => 'tabs', 'label' => 'Fases del proyecto', 'orientation' => 'horizontal', 'items' => [
                                    ['title' => 'Descubrimiento', 'children' => [
                                        ['type' => 'heading', 'text' => 'Entender el objetivo', 'level' => 'h3'],
                                        ['type' => 'rich_text', 'content' => 'Definimos audiencia, mensajería y mercados activos.', 'format' => 'markdown'],
                                        ['type' => 'list', 'items' => [['text' => 'Entrevistas'], ['text' => 'Mapa de contenidos'], ['text' => 'Prioridades SEO']]],
                                    ]],
                                    ['title' => 'Diseño', 'children' => [
                                        ['type' => 'heading', 'text' => 'Prototipo con tu marca', 'level' => 'h3'],
                                        ['type' => 'rich_text', 'content' => 'Prototipamos la web con tu identidad y validamos cada página contigo.', 'format' => 'markdown'],
                                        ['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Boceto de composición', 'fit' => 'cover'],
                                    ]],
                                    ['title' => 'Publicación', 'children' => [
                                        ['type' => 'heading', 'text' => 'Revisar y lanzar', 'level' => 'h3'],
                                        ['type' => 'rich_text', 'content' => 'Traducimos, configuramos la analítica y publicamos sin sorpresas.', 'format' => 'markdown'],
                                        ['type' => 'button', 'label' => 'Ver checklist', 'url' => '/contacto', 'variant' => 'primary', 'action' => 'link'],
                                    ]],
                                ]],
                            ]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-carrusel',
                'title' => 'Carrusel de diapositivas',
                'description' => 'Cabecera y carrusel con diapositivas en dos columnas internas.',
                'sections' => [
                    $this->section(
                        [
                            'content_width' => 'centered',
                            'animation' => 'fadeIn',
                            'padding' => '3rem 1.5rem 1rem',
                            'appearance' => ['base' => ['text_align' => 'center']],
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Destacados', 'level' => 'h2'],
                                ['type' => 'rich_text', 'content' => 'Tres proyectos recientes que muestran cómo trabajamos.', 'format' => 'markdown'],
                            ]),
                        ],
                    ),
                    $this->section(
                        [
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'padding' => '1rem 1.5rem 3rem',
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'carousel', 'label' => 'Carrusel de destacados', 'autoplay' => true, 'interval' => 6000, 'loop' => true, 'show_arrows' => true, 'show_dots' => true, 'items' => [
                                    ['label' => 'Uno', 'children' => [
                                        ['type' => 'heading', 'text' => 'Clínica Aurora: un 38 % más de citas', 'level' => 'h3'],
                                        ['type' => 'rich_text', 'content' => 'Nueva web con reserva online y recordatorios automáticos por correo.', 'format' => 'markdown'],
                                        ['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Recepción luminosa de una clínica', 'fit' => 'cover'],
                                        ['type' => 'button', 'label' => 'Saber más', 'url' => '/contacto', 'variant' => 'primary', 'action' => 'link'],
                                    ]],
                                    ['label' => 'Dos', 'children' => [
                                        ['type' => 'heading', 'text' => 'Bodegas Lumbre: tienda en tres idiomas', 'level' => 'h3'],
                                        ['type' => 'rich_text', 'content' => 'Catálogo con envíos a toda la Unión Europea y pago seguro.', 'format' => 'markdown'],
                                        ['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => 'Botellas de vino sobre una barrica', 'fit' => 'cover'],
                                    ]],
                                    ['label' => 'Tres', 'children' => [
                                        ['type' => 'heading', 'text' => 'Estudio Norte: portfolio en minutos', 'level' => 'h3'],
                                        ['type' => 'rich_text', 'content' => 'El propio equipo publica sus proyectos nuevos desde el panel.', 'format' => 'markdown'],
                                        ['type' => 'button', 'label' => 'Pedir presupuesto', 'url' => '/contacto', 'variant' => 'primary', 'action' => 'link'],
                                    ]],
                                ]],
                            ]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-aviso',
                'title' => 'Aviso destacado',
                'description' => 'Franja de aviso con acción secundaria en dos columnas.',
                'sections' => [
                    $this->section(
                        [
                            'bg_type' => 'color',
                            'bg_color' => 'var(--cms-surface-2)',
                            'content_width' => 'full',
                            'animation' => 'fadeInDown',
                            'padding' => '1.25rem 1.5rem',
                            'divider_bottom' => ['type' => 'spikes', 'color' => 'var(--cms-surface)', 'height' => 28, 'flip' => false],
                        ],
                        '2-1',
                        [
                            $this->column([
                                ['type' => 'alert', 'tone' => 'info', 'dismissible' => true, 'label' => 'Aviso', 'content' => 'Del 23 de diciembre al 6 de enero atendemos solo por correo. Los pedidos se enviarán a partir del 7 de enero.'],
                            ]),
                            $this->column([
                                ['type' => 'button', 'label' => 'Ver horarios', 'url' => '/noticias', 'variant' => 'secondary', 'action' => 'link'],
                            ], [
                                'appearance' => ['base' => ['display' => 'flex', 'align_items' => 'center', 'justify_content' => 'end']],
                            ]),
                        ],
                    ),
                ],
            ],
            [
                'slug' => 'plantilla-equipo',
                'title' => 'Equipo',
                'description' => 'Introducción y tres perfiles en columnas con tarjeta.',
                'sections' => [
                    $this->section(
                        [
                            'content_width' => 'centered',
                            'animation' => 'fadeIn',
                            'padding' => '3rem 1.5rem 1rem',
                            'appearance' => ['base' => ['text_align' => 'center']],
                        ],
                        '1',
                        [
                            $this->column([
                                ['type' => 'heading', 'text' => 'Quiénes lo hacen posible', 'level' => 'h2'],
                                ['type' => 'rich_text', 'content' => 'Un equipo pequeño y sénior que se implica en cada proyecto de principio a fin.', 'format' => 'markdown'],
                            ]),
                        ],
                    ),
                    $this->section(
                        [
                            'content_width' => 'full',
                            'animation' => 'fadeInUp',
                            'animation_delay' => '0.08s',
                            'padding' => '1rem 1.5rem 3.5rem',
                        ],
                        '3',
                        [
                            $this->personColumn('Ana López', 'Producto', 'Coordina cada proyecto y es tu contacto directo desde el primer día.'),
                            $this->personColumn('Luis Ortega', 'Ingeniería', 'Se asegura de que tu web cargue rápido, sea segura y crezca contigo.'),
                            $this->personColumn('Marta Ruiz', 'Diseño', 'Convierte tu marca en interfaces claras, accesibles y memorables.'),
                        ],
                    ),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $styling
     * @param  list<array<string, mixed>>  $columns
     * @return array{type: string, styling: array<string, mixed>, content: array<string, mixed>}
     */
    private function section(array $styling, string $layout, array $columns, string $type = 'text'): array
    {
        return [
            'type' => $type,
            'styling' => $styling,
            'content' => [
                'columns_layout' => $layout,
                'columns' => $columns,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $style
     * @return array{blocks: list<array<string, mixed>>, style: array<string, mixed>}
     */
    private function column(array $blocks, array $style = []): array
    {
        return ['blocks' => $blocks, 'style' => $style];
    }

    /** @return array{blocks: list<array<string, mixed>>, style: array<string, mixed>} */
    private function featureColumn(string $icon, string $title, string $body): array
    {
        return $this->column([
            ['type' => 'icon', 'icon' => $icon],
            ['type' => 'heading', 'text' => $title, 'level' => 'h3'],
            ['type' => 'rich_text', 'content' => $body, 'format' => 'markdown'],
        ], [
            'container_style' => 'cyber-card',
            'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'gap' => '0.65rem', 'padding' => '1.25rem', 'border_radius' => '0.75rem']],
        ]);
    }

    /** @return array{blocks: list<array<string, mixed>>, style: array<string, mixed>} */
    private function galleryColumn(string $alt, string $caption): array
    {
        return $this->column([
            ['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => $alt, 'caption' => $caption, 'fit' => 'cover', 'appearance' => ['base' => ['border_radius' => '0.75rem', 'min_height' => '14rem']]],
        ]);
    }

    /** @return array{blocks: list<array<string, mixed>>, style: array<string, mixed>} */
    private function personColumn(string $name, string $role, string $bio): array
    {
        return $this->column([
            ['type' => 'image', 'image_url' => '/images/placeholder.svg', 'image_alt' => $name, 'fit' => 'cover', 'appearance' => ['base' => ['border_radius' => '0.75rem', 'min_height' => '12rem']]],
            ['type' => 'heading', 'text' => $name, 'level' => 'h3'],
            ['type' => 'rich_text', 'content' => "**{$role}**\n\n{$bio}", 'format' => 'markdown'],
        ], [
            'container_style' => 'cyber-card',
            'appearance' => ['base' => ['display' => 'flex', 'flex_direction' => 'column', 'gap' => '0.5rem', 'padding' => '1rem', 'border_radius' => '0.75rem', 'text_align' => 'center', 'align_items' => 'center']],
        ]);
    }

    /**
     * @param  list<string>  $features
     * @return array{blocks: list<array<string, mixed>>, style: array<string, mixed>}
     */
    private function planColumn(string $name, string $amount, string $period, array $features, string $cta, bool $featured): array
    {
        return $this->column([
            ['type' => 'heading', 'text' => $name, 'level' => 'h3'],
            ['type' => 'price', 'amount' => $amount, 'currency' => '', 'period' => $period],
            ['type' => 'list', 'items' => array_map(static fn (string $feature): array => ['text' => $feature], $features)],
            ['type' => 'button', 'label' => $cta, 'url' => '/contacto', 'variant' => $featured ? 'primary' : 'secondary', 'action' => 'link'],
        ], [
            'container_style' => $featured ? 'neon-glow' : 'cyber-card',
            'appearance' => [
                'base' => array_filter([
                    'display' => 'flex',
                    'flex_direction' => 'column',
                    'gap' => '0.75rem',
                    'padding' => '1.5rem',
                    'border_radius' => '0.75rem',
                    'transform' => $featured ? 'scale(1.03)' : null,
                ]),
            ],
        ]);
    }
}
