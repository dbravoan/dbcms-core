<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class SeparatorBlock extends ComposableBlock
{
    protected const TYPE = 'separator';

    protected const NAME = 'Separador';

    protected const ICON = 'fa-solid fa-minus';

    protected const CATEGORY = 'layout';

    protected const DEFAULTS = ['label' => ''];

    protected const EXAMPLE = ['label' => 'Fin de la presentación de servicios'];

    protected const EXAMPLE_EN = ['label' => 'End of the services overview'];

    protected const TRANSLATABLE = ['label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['label' => self::text('Etiqueta accesible opcional')]);
    }
}
