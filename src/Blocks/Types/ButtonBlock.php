<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

final class ButtonBlock extends ComposableBlock
{
    protected const TYPE = 'button';

    protected const NAME = 'Botón';

    protected const ICON = 'fa-solid fa-arrow-pointer';

    protected const CATEGORY = 'content';

    protected const DEFAULTS = ['label' => '', 'action' => 'link', 'overlay_id' => '', 'url' => '', 'variant' => 'primary', 'new_window' => false];

    protected const EXAMPLE = ['label' => 'Solicitar presupuesto', 'url' => '/contacto', 'variant' => 'primary'];

    protected const EXAMPLE_EN = ['label' => 'Request a quote', 'url' => '/contact', 'variant' => 'primary'];

    protected const TRANSLATABLE = ['label'];

    public function getEditorSchema(): array
    {
        return self::withDefaults(['label' => self::text('Texto accesible', 120), 'action' => self::select('Acción', ['link' => 'Abrir enlace', 'open_overlay' => 'Abrir panel', 'close_overlay' => 'Cerrar panel activo']), 'overlay_id' => self::text('Identificador del panel', 80), 'url' => self::text('Destino', 2048, 'url'), 'variant' => self::select('Estilo', ['primary' => 'Principal', 'secondary' => 'Secundario', 'ghost' => 'Enlace']), 'new_window' => self::boolean('Abrir en una pestaña nueva')]);
    }
}
