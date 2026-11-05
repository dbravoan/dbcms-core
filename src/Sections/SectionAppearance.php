<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Services\Content\SafeContentRenderer;
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
    ];

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
            'length' => preg_match('/^(?:0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw))$/D', $value) === 1,
            'padding' => preg_match('/^(?:0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw))(?:\s+(?:0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw))){0,3}$/D', $value) === 1,
            'border' => in_array($value, ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'], true),
            'background' => in_array($value, ['none', 'color', 'image', 'video'], true),
            'background_behavior' => in_array($value, ['normal', 'fixed', 'parallax'], true),
            'container' => in_array($value, ['cms-card', 'cyber-card', 'cyber-glass', 'neon-glow', 'transparent', 'full-width-dark'], true),
            'content_width' => in_array($value, ['centered', 'full', 'narrow'], true),
            'layout' => in_array($value, ['1', '2', '3', '4', '1-2', '2-1'], true),
            'divider' => in_array($value, ['none', 'waves', 'tilt', 'curve', 'triangle', 'spikes'], true),
            'height' => is_numeric($value) && (float) $value >= 0 && (float) $value <= 2000,
            default => false,
        };
    }
}
