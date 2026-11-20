<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class DrawerBlock extends ComposableBlock
{
    protected const TYPE = 'drawer';

    protected const NAME = 'Panel lateral';

    protected const ICON = 'fa-solid fa-bars';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['overlay_id' => '', 'title' => '', 'trigger_label' => '', 'side' => 'right', 'backdrop' => true, 'backdrop_close' => true, 'modal' => true, 'children' => []];

    protected const EXAMPLE = ['overlay_id' => 'panel-detalles', 'title' => 'Detalles del servicio', 'trigger_label' => 'Ver qué incluye', 'children' => [['type' => 'heading', 'text' => 'Qué incluye el plan Profesional', 'level' => 'h3'], ['type' => 'rich_text', 'content' => "- Diseño a medida de hasta 15 páginas\n- Textos optimizados para SEO\n- Formación de una hora para tu equipo", 'format' => 'markdown']]];

    protected const EXAMPLE_EN = ['overlay_id' => 'panel-details', 'title' => 'Service details', 'trigger_label' => 'See what is included', 'children' => [['type' => 'heading', 'text' => 'What the Professional plan includes', 'level' => 'h3'], ['type' => 'rich_text', 'content' => "- Custom design of up to 15 pages\n- SEO-optimised copy\n- One-hour training session for your team", 'format' => 'markdown']]];

    protected const TRANSLATABLE = ['title', 'trigger_label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['overlay_id' => self::text('Identificador único', 80) + ['description' => 'Letra inicial; después letras, números, guiones o guion bajo. Permite abrir desde otros botones.'], 'title' => self::text('Título accesible'), 'trigger_label' => self::text('Texto del botón para abrir', 120), 'side' => self::select('Lado', ['left' => 'Izquierda', 'right' => 'Derecha', 'top' => 'Arriba', 'bottom' => 'Abajo']), 'backdrop' => self::boolean('Oscurecer el fondo'), 'backdrop_close' => self::boolean('Cerrar al pulsar fuera del panel'), 'modal' => self::boolean('Modal'), 'children' => self::childrenField()]);
    }
}
