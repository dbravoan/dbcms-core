<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class TabsBlock extends ComposableBlock
{
    protected const TYPE = 'tabs';

    protected const NAME = 'Pestañas';

    protected const ICON = 'fa-solid fa-folder';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['label' => '', 'orientation' => 'horizontal', 'initial_index' => 0, 'responsive_mode' => 'accordion', 'breakpoint' => 767, 'items' => []];

    protected const EXAMPLE = ['label' => 'Cómo trabajamos', 'items' => [
        ['title' => '1. Descubrimiento', 'children' => [['type' => 'heading', 'text' => 'Entendemos tu negocio', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'Entrevistamos a tu equipo, analizamos la competencia y definimos objetivos medibles.', 'format' => 'markdown']]],
        ['title' => '2. Diseño', 'children' => [['type' => 'heading', 'text' => 'Prototipo navegable', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'Te enseñamos un prototipo en la primera semana para validar estructura y estilo.', 'format' => 'markdown']]],
        ['title' => '3. Lanzamiento', 'children' => [['type' => 'heading', 'text' => 'Publicación sin sorpresas', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'Migramos contenidos, configuramos la analítica y formamos a tu equipo.', 'format' => 'markdown']]],
    ]];

    protected const EXAMPLE_EN = ['label' => 'How we work', 'items' => [
        ['title' => '1. Discovery', 'children' => [['type' => 'heading', 'text' => 'We get to know your business', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'We interview your team, analyse the competition and set measurable goals.', 'format' => 'markdown']]],
        ['title' => '2. Design', 'children' => [['type' => 'heading', 'text' => 'Clickable prototype', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'We show you a prototype in the first week to validate structure and style.', 'format' => 'markdown']]],
        ['title' => '3. Launch', 'children' => [['type' => 'heading', 'text' => 'A launch without surprises', 'level' => 'h3'], ['type' => 'rich_text', 'content' => 'We migrate content, set up analytics and train your team.', 'format' => 'markdown']]],
    ]];

    protected const TRANSLATABLE = ['label', 'items.*.title'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['label' => self::text('Etiqueta accesible'), 'orientation' => self::select('Orientación', ['horizontal' => 'Horizontal', 'vertical' => 'Vertical']), 'initial_index' => self::number('Panel inicial (desde cero)', 0, 99, true), 'responsive_mode' => self::select('Presentación en pantallas pequeñas', ['accordion' => 'Acordeón con los mismos paneles', 'tabs' => 'Mantener pestañas']), 'breakpoint' => self::number('Anchura máxima para acordeón (px)', 320, 1200, true), 'items' => self::items(['title' => self::text('Título de la pestaña'), 'children' => self::childrenField()], 'Pestañas')]);
    }
}
