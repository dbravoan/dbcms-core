<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class RatingBlock extends ComposableBlock
{
    protected const TYPE = 'rating';

    protected const NAME = 'Valoración';

    protected const ICON = 'fa-solid fa-star';

    protected const CATEGORY = 'content';

    protected const DEFAULTS = ['value' => 0, 'maximum' => 5, 'label' => ''];

    protected const EXAMPLE = ['value' => 4.8, 'label' => '4,8 de 5 según 320 opiniones verificadas'];

    protected const EXAMPLE_EN = ['value' => 4.8, 'label' => '4.8 out of 5 from 320 verified reviews'];

    protected const TRANSLATABLE = ['label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['value' => self::number('Valor', 0, 10), 'maximum' => self::number('Máximo', 1, 10, true), 'label' => self::text('Descripción de la valoración')]);
    }
}
