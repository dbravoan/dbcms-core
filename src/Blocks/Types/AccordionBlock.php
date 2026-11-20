<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class AccordionBlock extends ComposableBlock
{
    protected const TYPE = 'accordion';

    protected const NAME = 'Acordeón';

    protected const ICON = 'fa-solid fa-list';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['label' => '', 'multiple' => false, 'heading_level' => 'h3', 'icon_style' => 'plus_minus', 'icon_position' => 'end', 'items' => []];

    protected const EXAMPLE = ['label' => 'Preguntas frecuentes', 'items' => [
        ['title' => '¿Cuánto tarda en estar lista mi web?', 'open' => true, 'children' => [['type' => 'rich_text', 'content' => 'Una web corporativa de hasta diez páginas suele publicarse en **cuatro a seis semanas**, incluidas dos rondas de revisión.', 'format' => 'markdown']]],
        ['title' => '¿Puedo editar los contenidos yo mismo?', 'open' => false, 'children' => [['type' => 'rich_text', 'content' => 'Sí. El panel permite cambiar textos, imágenes y secciones sin tocar código, y cada cambio queda en el historial.', 'format' => 'markdown']]],
        ['title' => '¿Incluye mantenimiento y copias de seguridad?', 'open' => false, 'children' => [['type' => 'rich_text', 'content' => 'Todos los planes incluyen actualizaciones de seguridad, copia diaria y soporte por correo en horario laboral.', 'format' => 'markdown']]],
    ]];

    protected const EXAMPLE_EN = ['label' => 'Frequently asked questions', 'items' => [
        ['title' => 'How long until my website is ready?', 'open' => true, 'children' => [['type' => 'rich_text', 'content' => 'A corporate website of up to ten pages is usually live in **four to six weeks**, including two review rounds.', 'format' => 'markdown']]],
        ['title' => 'Can I edit the content myself?', 'open' => false, 'children' => [['type' => 'rich_text', 'content' => 'Yes. The panel lets you change copy, images and sections without touching code, and every change is kept in the history.', 'format' => 'markdown']]],
        ['title' => 'Are maintenance and backups included?', 'open' => false, 'children' => [['type' => 'rich_text', 'content' => 'Every plan includes security updates, daily backups and email support during business hours.', 'format' => 'markdown']]],
    ]];

    protected const TRANSLATABLE = ['label', 'items.*.title'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['label' => self::text('Etiqueta accesible'), 'multiple' => self::boolean('Permitir varios paneles abiertos'), 'heading_level' => self::select('Nivel de los títulos', ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6']), 'icon_style' => self::select('Iconos', ['plus_minus' => 'Más y menos', 'chevron' => 'Flechas', 'none' => 'Sin icono adicional']), 'icon_position' => self::select('Posición del icono', ['end' => 'Final', 'start' => 'Inicio']), 'items' => self::items(['title' => self::text('Título del panel'), 'open' => self::boolean('Abierto al cargar'), 'children' => self::childrenField()], 'Paneles')]);
    }
}
