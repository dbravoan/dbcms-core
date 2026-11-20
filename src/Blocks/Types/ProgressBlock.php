<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class ProgressBlock extends ComposableBlock
{
    protected const TYPE = 'progress';

    protected const NAME = 'Progreso';

    protected const ICON = 'fa-solid fa-bars-progress';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['label' => '', 'mode' => 'static', 'value' => 0, 'minimum' => 0, 'maximum' => 100, 'unit' => '%'];

    protected const EXAMPLE = ['label' => 'Objetivo de la campaña solidaria', 'value' => 72];

    protected const EXAMPLE_EN = ['label' => 'Charity campaign goal', 'value' => 72];

    protected const TRANSLATABLE = ['label', 'unit'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['label' => self::text('Etiqueta accesible'), 'mode' => self::select('Modo', ['static' => 'Valor manual', 'reading' => 'Progreso de lectura de la página']), 'value' => self::number('Valor', -1000000000, 1000000000), 'minimum' => self::number('Mínimo', -1000000000, 1000000000), 'maximum' => self::number('Máximo', -1000000000, 1000000000), 'unit' => self::text('Unidad', 20)]);
    }
}
