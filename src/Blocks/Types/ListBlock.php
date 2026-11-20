<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class ListBlock extends ComposableBlock
{
    protected const TYPE = 'list';

    protected const NAME = 'Lista';

    protected const ICON = 'fa-solid fa-list';

    protected const CATEGORY = 'content';

    protected const DEFAULTS = ['ordered' => false, 'items' => []];

    protected const EXAMPLE = ['items' => [
        ['text' => 'Diseño adaptado a móvil, tableta y escritorio', 'url' => ''],
        ['text' => 'Textos revisados y optimizados para buscadores', 'url' => ''],
        ['text' => 'Formularios conectados con tu correo y tu CRM', 'url' => ''],
        ['text' => 'Soporte técnico en menos de 24 horas laborables', 'url' => ''],
    ]];

    protected const EXAMPLE_EN = ['items' => [
        ['text' => 'Design adapted to mobile, tablet and desktop', 'url' => ''],
        ['text' => 'Copy reviewed and optimised for search engines', 'url' => ''],
        ['text' => 'Forms connected to your inbox and CRM', 'url' => ''],
        ['text' => 'Technical support within 24 business hours', 'url' => ''],
    ]];

    protected const TRANSLATABLE = ['items.*.text'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['ordered' => self::boolean('Lista numerada'), 'items' => self::items(['text' => self::text('Texto', 2000, 'textarea'), 'url' => self::text('Destino opcional', 2048, 'url')])]);
    }
}
