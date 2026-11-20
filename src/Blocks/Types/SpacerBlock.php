<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class SpacerBlock extends ComposableBlock
{
    protected const TYPE = 'spacer';

    protected const NAME = 'Espaciador';

    protected const ICON = 'fa-solid fa-up-down';

    protected const CATEGORY = 'layout';

    protected const DEFAULTS = ['height' => 32];

    protected const EXAMPLE = ['height' => 48];

    protected const TRANSLATABLE = [];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['height' => self::number('Altura en píxeles', 0, 400, true)]);
    }
}
