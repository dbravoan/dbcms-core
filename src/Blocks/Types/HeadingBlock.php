<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class HeadingBlock extends ComposableBlock
{
    protected const TYPE = 'heading';

    protected const NAME = 'Título';

    protected const ICON = 'fa-solid fa-heading';

    protected const CATEGORY = 'content';

    protected const DEFAULTS = ['text' => '', 'level' => 'h2'];

    protected const EXAMPLE = ['text' => 'Diseñamos webs que convierten visitas en clientes', 'level' => 'h2'];

    protected const EXAMPLE_EN = ['text' => 'We design websites that turn visitors into customers', 'level' => 'h2'];

    protected const TRANSLATABLE = ['text'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['text' => self::text('Texto del título'), 'level' => self::select('Nivel semántico', ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6'])]);
    }
}
