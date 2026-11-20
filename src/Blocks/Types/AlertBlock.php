<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\Contracts\BlockPayloadCompatibilityInterface;

final class AlertBlock extends ComposableBlock implements BlockPayloadCompatibilityInterface
{
    protected const TYPE = 'alert';

    protected const NAME = 'Aviso';

    protected const ICON = 'fa-solid fa-circle-info';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['label' => '', 'tone' => 'info', 'dismissible' => false, 'content' => ''];

    protected const EXAMPLE = ['label' => 'Aviso de horario', 'tone' => 'info', 'content' => '**Horario de verano:** del 1 de julio al 31 de agosto atendemos de lunes a viernes de 8:00 a 15:00.'];

    protected const EXAMPLE_EN = ['label' => 'Opening hours notice', 'tone' => 'info', 'content' => '**Summer hours:** from 1 July to 31 August we are open Monday to Friday, 8:00 to 15:00.'];

    protected const TRANSLATABLE = ['label', 'content'];

    public function retiredPayloadKeys(): array
    {
        return ['children'];
    }

    public function getEditorSchema(): array
    {
        return self::withDefaults([
            'label' => self::text('Etiqueta accesible'),
            'tone' => self::select('Tono', ['info' => 'Información', 'success' => 'Éxito', 'warning' => 'Aviso', 'danger' => 'Error']),
            'dismissible' => self::boolean('Permitir cerrar'),
            'content' => self::text('Mensaje', 10000, 'textarea') + ['description' => 'Texto del aviso. Admite Markdown básico.'],
        ]);
    }
}
