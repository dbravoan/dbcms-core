<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Blocks\BlockStyle;
use DbCms\Core\Services\Content\SafeContentRenderer;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;
use Illuminate\Validation\ValidationException;

/** Typed appearance values shared by editorial writes and defensive rendering. */
final class SectionAppearance
{
    private const FIELDS = [
        'bg_color' => 'color', 'bg_image_overlay' => 'color', 'bg_video_overlay' => 'color',
        'bg_image_url' => 'url', 'bg_video_url' => 'url', 'bg_video_poster' => 'url',
        'animation' => 'animation', 'animation_delay' => 'time', 'animation_duration' => 'time',
        'padding' => 'padding',
        'bg_type' => 'background', 'bg_behavior' => 'background_behavior',
        'container_style' => 'container', 'content_width' => 'content_width', 'columns_layout' => 'layout',
        'bg_position' => 'position', 'bg_size' => 'size', 'bg_repeat' => 'repeat',
        'bg_video_fit' => 'fit', 'bg_video_position' => 'position',
        'bg_external_service' => 'service',
        'content_max_width' => 'length',
    ];

    /** Canonical media/motion fields; the inspector must not invent a second schema.
     * @return array<string,array<string,mixed>> */
    public static function properties(): array
    {
        $select = static fn (string $label, array $options, string $group = 'background'): array => ['type' => 'select', 'control' => 'select', 'label' => $label, 'group' => $group, 'options' => ['' => 'Heredar'] + (array_is_list($options) ? array_combine($options, $options) : $options)];
        $positions = ['center' => 'Centro', 'top' => 'Arriba', 'bottom' => 'Abajo', 'left' => 'Izquierda', 'right' => 'Derecha', 'top left' => 'Arriba a la izquierda', 'top right' => 'Arriba a la derecha', 'bottom left' => 'Abajo a la izquierda', 'bottom right' => 'Abajo a la derecha'];
        $fits = ['cover' => 'Cubrir', 'contain' => 'Contener', 'fill' => 'Estirar', 'none' => 'Tamaño original', 'scale-down' => 'Reducir si es necesario'];
        $fields = [
            'bg_type' => $select('Fondo', ['none' => 'Sin fondo', 'color' => 'Color', 'gradient' => 'Degradado', 'image' => 'Imagen', 'video' => 'Vídeo']),
            'bg_image_url' => ['type' => 'media', 'control' => 'media', 'label' => 'Imagen de fondo', 'group' => 'background', 'media_type' => 'image'],
            'bg_video_url' => ['type' => 'media', 'control' => 'media', 'label' => 'Vídeo de fondo', 'group' => 'background', 'media_type' => 'video'],
            'bg_video_poster' => ['type' => 'media', 'control' => 'media', 'label' => 'Imagen de espera', 'group' => 'background', 'media_type' => 'image'],
            'bg_behavior' => $select('Comportamiento', ['normal' => 'Normal', 'fixed' => 'Fijo', 'parallax' => 'Paralaje']),
            'bg_position' => $select('Posición de imagen', $positions),
            'bg_size' => $select('Ajuste de imagen', ['cover' => 'Cubrir', 'contain' => 'Contener', 'auto' => 'Tamaño original']),
            'bg_repeat' => $select('Repetición de imagen', ['no-repeat' => 'No repetir', 'repeat' => 'Repetir en ambas direcciones', 'repeat-x' => 'Repetir horizontalmente', 'repeat-y' => 'Repetir verticalmente']),
            'bg_video_fit' => $select('Ajuste de vídeo', $fits),
            'bg_video_position' => $select('Posición de vídeo', $positions),
            'bg_external_service' => ['type' => 'select', 'control' => 'select', 'label' => 'Servicio externo revisado', 'group' => 'background', 'options' => ['' => 'Alojamiento local o necesario'] + array_map(static fn (array $service): string => $service['provider'], app(ExternalContentPolicy::class)->services())],
            'content_width' => $select('Anchura interior', ['centered' => 'Centrada', 'narrow' => 'Estrecha', 'full' => 'Completa', 'custom' => 'Personalizada'], 'dimensions'),
            'content_max_width' => ['type' => 'text', 'control' => 'length', 'label' => 'Anchura interior personalizada', 'group' => 'dimensions', 'units' => ['px', 'rem', 'em', '%', 'vh', 'vw'], 'supports_theme_variables' => true],
            'container_style' => $select('Acabado heredado', ['cms-card' => 'Tarjeta del tema', 'cyber-card' => 'Tarjeta anterior', 'cyber-glass' => 'Cristal', 'neon-glow' => 'Resplandor', 'transparent' => 'Transparente', 'full-width-dark' => 'Superficie secundaria'], 'paint'),
            'animation' => $select('Entrada', ['none' => 'Sin animación', 'fadeIn' => 'Aparecer', 'fadeInUp' => 'Desde abajo', 'fadeInDown' => 'Desde arriba', 'fadeInLeft' => 'Desde la izquierda', 'fadeInRight' => 'Desde la derecha', 'zoomIn' => 'Acercar', 'bounceIn' => 'Rebotar', 'flipInX' => 'Giro sobre el eje X', 'video-playback' => 'Reproducir fondo de vídeo'], 'motion'),
            'scroll_magic.scrub' => $select('Vincular al desplazamiento', ['none' => 'Sin vinculación', 'rotate' => 'Rotación', 'playback' => 'Reproducción de vídeo', 'parallax' => 'Paralaje'], 'motion'),
        ];
        foreach (['bg_color' => 'Color de fondo', 'bg_image_overlay' => 'Capa sobre imagen', 'bg_video_overlay' => 'Capa sobre vídeo'] as $key => $label) {
            $fields[$key] = ['type' => 'text', 'control' => 'color', 'label' => $label, 'group' => 'background', 'supports_theme_variables' => true];
        }
        foreach (['animation_delay' => 'Retraso', 'animation_duration' => 'Duración'] as $key => $label) {
            $fields[$key] = ['type' => 'text', 'control' => 'length', 'label' => $label, 'group' => 'motion', 'units' => ['ms', 's']];
        }
        foreach (['top' => 'superior', 'bottom' => 'inferior'] as $side => $label) {
            $fields['divider_'.$side.'.type'] = $select('Separador '.$label, ['none' => 'Sin separador', 'waves' => 'Ondas', 'tilt' => 'Inclinado', 'curve' => 'Curva', 'triangle' => 'Triángulo', 'spikes' => 'Picos'], 'borders');
            $fields['divider_'.$side.'.color'] = ['type' => 'text', 'control' => 'color', 'label' => 'Color '.$label, 'group' => 'borders', 'supports_theme_variables' => true];
            $fields['divider_'.$side.'.height'] = ['type' => 'number', 'control' => 'number', 'label' => 'Altura '.$label, 'group' => 'borders', 'min' => 0, 'max' => 2000, 'units' => ['px']];
            $fields['divider_'.$side.'.flip'] = ['type' => 'boolean', 'control' => 'boolean', 'label' => 'Invertir '.$label, 'group' => 'borders'];
        }

        return $fields;
    }

    /** @return list<string> */
    private static function positions(): array
    {
        return ['center', 'top', 'bottom', 'left', 'right', 'top left', 'top right', 'bottom left', 'bottom right'];
    }

    /** @param array<string, mixed> $payload */
    public static function validate(array $payload): void
    {
        if (self::sanitize($payload) !== $payload) {
            throw ValidationException::withMessages(['sections_json' => 'Invalid section appearance value.']);
        }
    }

    /**
     * Invalid persisted values are omitted; they never become HTML or CSS.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        if (array_key_exists('appearance', $payload)) {
            try {
                if (! is_array($payload['appearance'])) {
                    throw ValidationException::withMessages(['appearance' => 'Invalid appearance.']);
                }
                BlockStyle::validate($payload['appearance']);
            } catch (ValidationException) {
                unset($payload['appearance']);
            }
        }
        foreach (self::FIELDS as $key => $type) {
            if (array_key_exists($key, $payload) && ! self::valid($payload[$key], $type)) {
                unset($payload[$key]);
            }
        }
        foreach (['scroll_magic', 'border_top', 'border_bottom', 'divider_top', 'divider_bottom'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            if (! is_array($payload[$key])) {
                unset($payload[$key]);

                continue;
            }
            $fields = match ($key) {
                'scroll_magic' => ['behavior' => 'animation', 'delay' => 'time', 'duration' => 'time', 'scrub' => 'scrub'],
                'border_top', 'border_bottom' => ['enabled' => 'boolean', 'color' => 'color', 'width' => 'length', 'style' => 'border'],
                default => ['type' => 'divider', 'color' => 'color', 'height' => 'height', 'flip' => 'boolean'],
            };
            foreach ($fields as $field => $type) {
                if (array_key_exists($field, $payload[$key]) && ! self::valid($payload[$key][$field], $type)) {
                    unset($payload[$key][$field]);
                }
            }
        }

        return $payload;
    }

    private static function valid(mixed $value, string $type): bool
    {
        if ($type === 'boolean') {
            return in_array($value, [true, false, 0, 1, '0', '1'], true);
        }
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return false;
        }
        $value = (string) $value;
        if ($value === '') {
            return true;
        }
        if (strlen($value) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            return false;
        }

        return match ($type) {
            'color' => preg_match('/^(?:#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})|[a-z]+|var\(--[a-z0-9-]+\)|(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([0-9a-z.,%+\-\s\/]+\))$/iD', $value) === 1,
            'url' => ! preg_match('/[<>"\'\\\\]/', $value) && (new SafeContentRenderer)->isSafeUrl($value, true),
            'animation' => in_array($value, ['none', 'fadeIn', 'fadeInUp', 'fadeInDown', 'fadeInLeft', 'fadeInRight', 'zoomIn', 'bounceIn', 'flipInX', 'video-playback'], true),
            'scrub' => in_array($value, ['none', 'rotate', 'playback', 'parallax'], true),
            'time' => preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:ms|s)$/D', $value) === 1,
            'length' => preg_match('/^(?:var\(--[a-z0-9-]+\)|0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw))$/iD', $value) === 1,
            'padding' => preg_match('/^(?:0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw))(?:\s+(?:0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw))){0,3}$/D', $value) === 1,
            'border' => in_array($value, ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'], true),
            'background' => in_array($value, ['none', 'color', 'gradient', 'image', 'video'], true),
            'background_behavior' => in_array($value, ['normal', 'fixed', 'parallax'], true),
            'position' => in_array($value, self::positions(), true),
            'size' => in_array($value, ['cover', 'contain', 'auto'], true),
            'repeat' => in_array($value, ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'], true),
            'fit' => in_array($value, ['cover', 'contain', 'fill', 'none', 'scale-down'], true),
            'service' => preg_match('/^[a-z][a-z0-9_-]*$/D', $value) === 1,
            'container' => in_array($value, ['cms-card', 'cyber-card', 'cyber-glass', 'neon-glow', 'transparent', 'full-width-dark'], true),
            'content_width' => in_array($value, ['centered', 'full', 'narrow', 'custom'], true),
            'layout' => in_array($value, ['1', '2', '3', '4', '1-2', '2-1'], true),
            'divider' => in_array($value, ['none', 'waves', 'tilt', 'curve', 'triangle', 'spikes'], true),
            'height' => is_numeric($value) && (float) $value >= 0 && (float) $value <= 2000,
            default => false,
        };
    }
}
