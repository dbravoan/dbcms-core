<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Types;

use DbCms\Core\Blocks\BlockStorageDisclosure;
use DbCms\Core\Blocks\Contracts\BlockPayloadCompatibilityInterface;

final class CountdownBlock extends ComposableBlock implements BlockPayloadCompatibilityInterface
{
    protected const TYPE = 'countdown';

    protected const NAME = 'Cuenta atrás';

    protected const ICON = 'fa-solid fa-hourglass-half';

    protected const CATEGORY = 'interactive';

    protected const DEFAULTS = ['label' => '', 'mode' => 'absolute', 'deadline' => '', 'duration' => 900, 'finished_text' => '', 'expired_action' => 'message', 'redirect_url' => '', 'persistence' => 'memory', 'storage_purpose' => 'preferences'];

    protected const EXAMPLE = ['label' => 'La oferta de lanzamiento termina en', 'mode' => 'visit', 'duration' => 900, 'finished_text' => 'La oferta ha terminado, pero puedes escribirnos y te preparamos un presupuesto a medida.'];

    protected const EXAMPLE_EN = ['label' => 'The launch offer ends in', 'mode' => 'visit', 'duration' => 900, 'finished_text' => 'The offer has ended, but you can still write to us for a tailored quote.'];

    protected const TRANSLATABLE = ['label', 'finished_text'];

    public function retiredPayloadKeys(): array
    {
        return ['completion_children'];
    }

    public function getDefaultPayload(): array
    {
        return array_replace(parent::getDefaultPayload(), ['storage_purpose' => self::configuredPurpose()]);
    }

    private static function configuredPurpose(): string
    {
        $purpose = (string) config('cms.blocks.countdown_storage.purpose', 'preferences');

        return in_array($purpose, ['preferences', 'analytics', 'marketing', 'external'], true) ? $purpose : 'preferences';
    }

    public function getEditorSchema(): array
    {
        $persistence = ['memory' => 'Solo memoria; se reinicia al recargar'];
        if (app(BlockStorageDisclosure::class)->available()) {
            $persistence += ['session' => 'Conservar en esta sesión con autorización', 'local' => 'Conservar entre visitas con autorización'];
        }

        $schema = self::withDefaults([
            'label' => self::text('Etiqueta'),
            'mode' => self::select('Modo', ['absolute' => 'Fecha absoluta con zona horaria', 'visit' => 'Duración de esta visita']),
            'deadline' => ['type' => 'text', 'label' => 'Fecha ISO 8601 con zona horaria', 'rules' => ['nullable', 'string', 'date', 'regex:/^\\d{4}-\\d\\d-\\d\\dT\\d\\d:\\d\\d(?::\\d\\d)?(?:Z|[+-]\\d\\d:\\d\\d)$/D']] + ['format' => 'datetime-offset', 'description' => 'Selecciona fecha, hora y desplazamiento UTC explícito; no se adivina la zona del navegador.'],
            'duration' => self::number('Duración de visita en segundos', 1, 31536000, true),
            'finished_text' => self::text('Texto al terminar'),
            'expired_action' => self::select('Al finalizar', ['message' => 'Mostrar el mensaje final', 'hide' => 'Ocultar el bloque', 'redirect' => 'Ir a una URL']),
            'redirect_url' => self::text('Destino al finalizar', 2048, 'url') + ['show_when' => ['field' => 'expired_action', 'equals' => 'redirect']],
            'persistence' => self::select('Conservación de la visita', $persistence) + ['show_when' => ['field' => 'mode', 'equals' => 'visit']],
            'storage_purpose' => self::select('Finalidad del almacenamiento autorizado por el proyecto', [self::configuredPurpose() => ucfirst(self::configuredPurpose())]) + ['show_when' => ['field' => 'persistence', 'not_equals' => 'memory']],
        ]);
        $schema['storage_purpose']['default'] = self::configuredPurpose();

        return $schema;
    }
}
