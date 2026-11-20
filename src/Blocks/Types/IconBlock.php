<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class IconBlock extends ComposableBlock
{
    protected const TYPE = 'icon';

    protected const NAME = 'Icono';

    protected const ICON = 'fa-solid fa-icons';

    protected const CATEGORY = 'content';

    protected const DEFAULTS = ['icon' => 'fa-solid fa-star', 'label' => '', 'decorative' => true];

    protected const EXAMPLE = ['icon' => 'fa-solid fa-leaf', 'label' => 'Compromiso sostenible', 'decorative' => false];

    protected const EXAMPLE_EN = ['icon' => 'fa-solid fa-leaf', 'label' => 'Sustainability commitment', 'decorative' => false];

    protected const TRANSLATABLE = ['label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['icon' => self::text('Clase Font Awesome local', 100) + ['description' => 'Dos clases locales: fa-solid fa-star. No admite HTML.'], 'label' => self::text('Nombre accesible'), 'decorative' => self::boolean('Decorativo')]);
    }
}
