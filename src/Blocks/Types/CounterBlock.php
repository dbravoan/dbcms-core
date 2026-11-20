<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class CounterBlock extends ComposableBlock
{
    protected const TYPE = 'counter';

    protected const NAME = 'Contador';

    protected const ICON = 'fa-solid fa-hashtag';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['value' => 0, 'start' => 0, 'duration' => 1200, 'decimals' => 0, 'prefix' => '', 'suffix' => '', 'label' => ''];

    protected const EXAMPLE = ['value' => 1250, 'duration' => 1600, 'suffix' => '+', 'label' => 'proyectos publicados desde 2012'];

    protected const EXAMPLE_EN = ['value' => 1250, 'duration' => 1600, 'suffix' => '+', 'label' => 'projects launched since 2012'];

    protected const TRANSLATABLE = ['prefix', 'suffix', 'label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['value' => self::number('Valor final', -1000000000000, 1000000000000), 'start' => self::number('Valor inicial', -1000000000000, 1000000000000), 'duration' => self::number('Duración en milisegundos', 0, 10000, true), 'decimals' => self::number('Decimales', 0, 6, true), 'prefix' => self::text('Prefijo', 80), 'suffix' => self::text('Sufijo', 80), 'label' => self::text('Etiqueta')]);
    }
}
