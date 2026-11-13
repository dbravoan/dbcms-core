<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use Illuminate\Validation\ValidationException;

/** Typed, finite CSS declarations. No free-form stylesheets cross the editorial boundary. */
final class BlockStyle
{
    private const ENUMS = [
        'display' => ['block', 'flex', 'grid', 'inline', 'inline-block', 'inline-flex', 'none'],
        'flex_direction' => ['row', 'row-reverse', 'column', 'column-reverse'],
        'flex_wrap' => ['nowrap', 'wrap', 'wrap-reverse'],
        'justify_content' => ['normal', 'start', 'end', 'center', 'space-between', 'space-around', 'space-evenly'],
        'align_items' => ['normal', 'start', 'end', 'center', 'stretch', 'baseline'],
        'align_self' => ['auto', 'start', 'end', 'center', 'stretch', 'baseline'],
        'position' => ['static', 'relative', 'absolute', 'fixed', 'sticky'],
        'overflow' => ['visible', 'hidden', 'auto', 'scroll', 'clip'],
        'text_align' => ['start', 'end', 'left', 'right', 'center', 'justify'],
        'font_style' => ['normal', 'italic', 'oblique'],
        'text_transform' => ['none', 'uppercase', 'lowercase', 'capitalize'],
        'text_decoration' => ['none', 'underline', 'line-through', 'overline'],
        'border_style' => ['none', 'solid', 'dotted', 'dashed', 'double'],
        'border_top_style' => ['none', 'solid', 'dotted', 'dashed', 'double', 'groove', 'ridge', 'inset', 'outset'],
        'border_right_style' => ['none', 'solid', 'dotted', 'dashed', 'double', 'groove', 'ridge', 'inset', 'outset'],
        'border_bottom_style' => ['none', 'solid', 'dotted', 'dashed', 'double', 'groove', 'ridge', 'inset', 'outset'],
        'border_left_style' => ['none', 'solid', 'dotted', 'dashed', 'double', 'groove', 'ridge', 'inset', 'outset'],
        'object_fit' => ['contain', 'cover', 'fill', 'none', 'scale-down'],
        'object_position' => ['center', 'top', 'bottom', 'left', 'right', 'top left', 'top right', 'bottom left', 'bottom right'],
        'visibility' => ['visible', 'hidden'],
        'grid_auto_flow' => ['row', 'column', 'row dense', 'column dense'],
    ];

    private const LENGTHS = ['width', 'height', 'min_width', 'max_width', 'min_height', 'max_height', 'gap', 'row_gap', 'column_gap', 'padding', 'padding_top', 'padding_right', 'padding_bottom', 'padding_left', 'margin', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'top', 'right', 'bottom', 'left', 'font_size', 'line_height', 'letter_spacing', 'border_width', 'border_top_width', 'border_right_width', 'border_bottom_width', 'border_left_width', 'border_radius', 'outline_width', 'outline_offset'];

    private const COLORS = ['color', 'background_color', 'border_color', 'border_top_color', 'border_right_color', 'border_bottom_color', 'border_left_color', 'outline_color'];

    /** @return array<string,array<string,mixed>> */
    public static function properties(): array
    {
        $result = [];
        foreach (self::ENUMS as $key => $options) {
            $result[$key] = ['type' => 'select', 'label' => self::label($key), 'options' => ['' => 'Heredar'] + array_combine($options, $options)];
        }
        foreach ([...self::LENGTHS, ...self::COLORS, 'font_family', 'font_weight', 'opacity', 'order', 'z_index', 'grid_template_columns', 'grid_template_rows', 'grid_column', 'grid_row', 'transform', 'filter', 'box_shadow', 'text_shadow', 'background_image', 'transition_duration'] as $key) {
            $result[$key] = ['type' => 'text', 'label' => self::label($key)];
        }

        foreach ($result as $key => &$field) {
            $group = match (true) {
                in_array($key, ['width', 'height', 'min_width', 'max_width', 'min_height', 'max_height', 'object_fit', 'object_position'], true) => 'dimensions',
                str_starts_with($key, 'margin') || str_starts_with($key, 'padding') || str_contains($key, 'gap') => 'spacing',
                str_starts_with($key, 'font') || in_array($key, ['color', 'line_height', 'letter_spacing', 'text_align', 'text_transform', 'text_decoration'], true) => 'typography',
                str_starts_with($key, 'border') || str_starts_with($key, 'outline') => 'borders',
                in_array($key, ['background_color', 'opacity'], true) => 'paint',
                in_array($key, ['box_shadow', 'text_shadow', 'transition_duration'], true) => 'effects',
                in_array($key, ['transform', 'filter', 'background_image'], true) => 'advanced',
                default => 'layout',
            };
            $field += ['group' => $group, 'control' => isset(self::ENUMS[$key]) ? 'select' : (in_array($key, self::LENGTHS, true) ? 'length' : (in_array($key, self::COLORS, true) ? 'color' : (in_array($key, ['opacity', 'order', 'z_index'], true) ? 'number' : 'text'))),
                'units' => in_array($key, self::LENGTHS, true) ? ['', 'px', 'rem', 'em', '%', 'vh', 'vw', 'svh', 'dvh', 'ch'] : [],
                'supports_theme_variables' => ! isset(self::ENUMS[$key]), 'applicability' => in_array($key, ['object_fit', 'object_position'], true) ? ['image', 'video', 'media-background'] : []];
            if (preg_match('/^border_(top|right|bottom|left)_(width|style|color)$/D', $key, $parts)) {
                $field['side'] = $parts[1];
                $field['component'] = $parts[2];
            }
        }
        unset($field);
        $result['font_family'] += ['options' => ['' => 'Heredar', 'system-ui' => 'Sistema', 'sans-serif' => 'Sin serifas', 'serif' => 'Con serifas', 'monospace' => 'Monoespaciada']];
        $result['font_family']['control'] = 'select';
        $result['font_weight'] += ['options' => ['' => 'Heredar', 'normal' => 'Normal', 'bold' => 'Negrita', '100' => '100', '200' => '200', '300' => '300', '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800', '900' => '900']];
        $result['font_weight']['control'] = 'select';
        $result['transition_duration']['control'] = 'length';
        $result['transition_duration']['units'] = ['ms', 's'];
        $result['background_image']['control'] = 'gradient';
        $result['background_image']['group'] = 'background';
        $result['background_image']['gradient_types'] = ['linear' => 'Lineal', 'radial' => 'Radial'];
        $result['background_image']['gradient_defaults'] = ['type' => 'linear', 'angle' => 135, 'from' => 'var(--cms-surface)', 'to' => 'var(--cms-accent)'];
        foreach ([
            'text_align' => ['start' => 'Inicio', 'end' => 'Final', 'left' => 'Izquierda', 'right' => 'Derecha', 'center' => 'Centro', 'justify' => 'Justificado'],
            'justify_content' => ['normal' => 'Normal', 'start' => 'Inicio', 'end' => 'Final', 'center' => 'Centro', 'space-between' => 'Entre elementos', 'space-around' => 'Alrededor', 'space-evenly' => 'Espacio uniforme'],
            'align_items' => ['normal' => 'Normal', 'start' => 'Inicio', 'end' => 'Final', 'center' => 'Centro', 'stretch' => 'Estirar', 'baseline' => 'Línea base'],
        ] as $key => $options) {
            $result[$key]['control'] = 'alignment';
            $result[$key]['options'] = ['' => 'Heredar'] + $options;
        }
        $result['object_fit']['options'] = ['' => 'Heredar', 'contain' => 'Contener', 'cover' => 'Cubrir', 'fill' => 'Estirar', 'none' => 'Tamaño original', 'scale-down' => 'Reducir si es necesario'];
        $result['object_position']['options'] = ['' => 'Heredar', 'center' => 'Centro', 'top' => 'Arriba', 'bottom' => 'Abajo', 'left' => 'Izquierda', 'right' => 'Derecha', 'top left' => 'Arriba a la izquierda', 'top right' => 'Arriba a la derecha', 'bottom left' => 'Abajo a la izquierda', 'bottom right' => 'Abajo a la derecha'];
        $result['box_shadow']['control'] = 'select';
        $result['box_shadow']['options'] = ['none' => 'Sin sombra', '0 1px 3px rgba(0,0,0,0.12)' => 'Suave', '0 4px 12px rgba(0,0,0,0.18)' => 'Media', '0 12px 32px rgba(0,0,0,0.28)' => 'Intensa'];
        $result['text_shadow']['control'] = 'select';
        $result['text_shadow']['options'] = ['none' => 'Sin sombra', '0 1px 2px rgba(0,0,0,0.18)' => 'Suave', '0 2px 5px rgba(0,0,0,0.25)' => 'Media', '0 4px 10px rgba(0,0,0,0.35)' => 'Intensa'];
        foreach (['grid_template_columns', 'grid_template_rows', 'grid_column', 'grid_row'] as $key) {
            $result[$key]['group'] = 'advanced';
        }

        return $result;
    }

    /** @return array<string,string> */
    public static function themeVariables(): array
    {
        return ['var(--cms-accent)' => 'Acento', 'var(--cms-fg)' => 'Texto', 'var(--cms-muted)' => 'Texto secundario', 'var(--cms-surface)' => 'Superficie', 'var(--cms-surface-2)' => 'Superficie secundaria', 'var(--cms-border)' => 'Borde', 'var(--cms-radius)' => 'Radio'];
    }

    /** @param array<string,mixed> $appearance */
    public static function validate(array $appearance): void
    {
        foreach ($appearance as $scope => $values) {
            if ($scope === 'extent') {
                if (! in_array($values, ['theme', 'viewport'], true)) {
                    self::invalid('extent');
                }

                continue;
            }
            if (! in_array($scope, ['base', 'tablet', 'mobile', 'hover', 'focus', 'active', 'selected', 'classes'], true)) {
                self::invalid((string) $scope);
            }
            if ($scope === 'classes') {
                if (! is_array($values) || ! array_is_list($values)) {
                    self::invalid('classes');
                }
                foreach ($values as $class) {
                    if (! is_string($class) || ! array_key_exists($class, (array) config('cms.blocks.classes', []))) {
                        self::invalid('classes');
                    }
                }

                continue;
            }
            if (! is_array($values)) {
                self::invalid((string) $scope);
            }
            foreach ($values as $key => $value) {
                if (! self::valid((string) $key, $value)) {
                    self::invalid((string) $key);
                }
            }
        }
    }

    /** @param array<string,mixed> $appearance */
    public static function stylesheet(string $selector, array $appearance, ?string $contentSelector = null, ?string $mediaSelector = null): string
    {
        self::validate($appearance);
        $base = [];
        foreach ((array) ($appearance['classes'] ?? []) as $class) {
            $base = array_replace($base, (array) config('cms.blocks.classes.'.$class, []));
        }
        self::validate(['base' => $base]);
        $appearance['base'] = array_replace($base, (array) ($appearance['base'] ?? []));
        $css = '';
        foreach (['base', 'tablet', 'mobile', 'hover', 'focus', 'active', 'selected'] as $scope) {
            $values = (array) ($appearance[$scope] ?? []);
            $sides = array_filter($values, static fn (string $key): bool => preg_match('/^border_(top|right|bottom|left)_/', $key) === 1, ARRAY_FILTER_USE_KEY);
            $values = array_diff_key($values, $sides) + $sides;
            if ($values === []) {
                continue;
            }
            // The surface owns placement and paint; its visual root owns child
            // layout and typography. Dimensions are applied once, while media
            // fitting uses only the owner's explicit media targets.
            $outer = $inner = $media = '';
            foreach ($values as $key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $declaration = str_replace('_', '-', (string) $key).':'.$value.';';
                if ($contentSelector === null) {
                    $outer .= $declaration;
                } elseif (in_array($key, ['object_fit', 'object_position'], true)) {
                    $media .= str_replace('_', '-', $key).':'.$value.'!important;';
                } elseif (! preg_match('/^(?:display$|flex_|justify_content$|align_items$|gap$|row_gap$|column_gap$|grid_template_|grid_auto_flow$|font_|line_height$|letter_spacing$|text_|color$)/', (string) $key)) {
                    $outer .= $declaration;
                } else {
                    $inner .= $declaration;
                    if ($key === 'display' && $value === 'none') {
                        $outer .= $declaration;
                    }
                }
            }
            $state = static fn (string $target): string => match ($scope) {
                'hover', 'active' => $target.':'.$scope,
                'focus' => $target.':focus-within',
                'selected' => $target.':is([aria-selected="true"],[data-selected="true"])',
                default => $target,
            };
            $rule = $outer === '' ? '' : $state($selector).'{'.$outer.'}';
            if ($inner !== '' && $contentSelector !== null) {
                $target = $scope === 'selected'
                    ? $contentSelector.' > :is([aria-selected="true"],[data-selected="true"]),'.$contentSelector.' > [data-tab-list] > :is([aria-selected="true"],[data-selected="true"]),'.$state($contentSelector)
                    : $state($contentSelector);
                $rule .= $target.'{'.$inner.'}';
            }
            if ($media !== '' && $contentSelector !== null) {
                $rule .= ($mediaSelector === null ? $state($selector).' > [data-cms-presentation-media] :is(img,video)' : str_replace($selector, $state($selector), $mediaSelector)).'{'.$media.'}';
            }
            $css .= match ($scope) {
                'tablet' => '@media(max-width:'.(int) config('cms.blocks.breakpoints.tablet', 1024).'px){'.$rule.'}',
                'mobile' => '@media(max-width:'.(int) config('cms.blocks.breakpoints.mobile', 767).'px){'.$rule.'}',
                default => $rule,
            };
        }

        return $css;
    }

    private static function valid(string $key, mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value) && $value !== null) {
            return false;
        }
        $value = (string) $value;
        if ($value === '') {
            return array_key_exists($key, self::properties());
        }
        if (strlen($value) > 1024 || preg_match('/[;{}<>\\\\\x00-\x1f]/', $value)) {
            return false;
        }
        if (isset(self::ENUMS[$key])) {
            return in_array($value, self::ENUMS[$key], true);
        }
        if (preg_match('/^var\(--[a-z0-9-]+\)$/iD', $value)) {
            return array_key_exists($key, self::properties());
        }
        if (in_array($key, self::LENGTHS, true)) {
            return preg_match('/^(?:auto|none|normal|fit-content|max-content|min-content|(?:-?(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw|svh|dvh|ch)?)(?:\s+-?(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw|svh|dvh|ch)?){0,3})$/D', $value) === 1;
        }
        if (in_array($key, self::COLORS, true)) {
            return preg_match('/^(?:#[0-9a-f]{3,8}|[a-z]+|(?:rgba?|hsla?|oklch)\([0-9.%+,\s\/-]+\))$/iD', $value) === 1;
        }

        return match ($key) {
            'font_family' => preg_match('/^[a-zA-Z0-9 ,"\'-]+$/D', $value) === 1,
            'font_weight' => in_array($value, ['normal', 'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900'], true),
            'opacity' => is_numeric($value) && (float) $value >= 0 && (float) $value <= 1,
            'order', 'z_index' => preg_match('/^-?\d{1,6}$/D', $value) === 1,
            'grid_column', 'grid_row' => preg_match('/^(?:auto|span \d{1,3}|\d{1,3}(?:\s*\/\s*(?:span )?\d{1,3})?)$/D', $value) === 1,
            'grid_template_columns', 'grid_template_rows' => preg_match('/^(?:none|(?:[\d.\s,%()+\/-]|fr|px|rem|em|auto|minmax|repeat|min-content|max-content|auto-fit|auto-fill)+)$/D', $value) === 1,
            'transition_duration' => preg_match('/^\d+(?:\.\d+)?(?:ms|s)$/D', $value) === 1,
            'transform' => preg_match('/^(?:none|(?:(?:translate[XY]?|scale[XY]?|rotate|skew[XY]?)\([-\d.\s,%a-z]+\)\s*)+)$/D', $value) === 1,
            'filter' => preg_match('/^(?:none|(?:(?:blur|brightness|contrast|grayscale|hue-rotate|invert|opacity|saturate|sepia)\([-\d.%a-z]+\)\s*)+)$/D', $value) === 1,
            'box_shadow', 'text_shadow' => preg_match('/^(?:none|[-\d.\s,#%()a-z]+)$/iD', $value) === 1,
            'background_image' => preg_match('/\b(?:url|image|cross-fade|element|paint|expression)\s*\(/i', $value) !== 1 && preg_match('/^(?:none|(?:(?:linear|radial)-gradient\([-\d.\s,#%()a-z\/]+\))(?:\s*,\s*(?:(?:linear|radial)-gradient\([-\d.\s,#%()a-z\/]+\)))*)$/iD', $value) === 1,
            default => false,
        };
    }

    private static function label(string $key): string
    {
        if (preg_match('/^border_(top|right|bottom|left)_(width|style|color)$/D', $key, $parts)) {
            return ['width' => 'Grosor', 'style' => 'Estilo', 'color' => 'Color'][$parts[2]].' del borde '.['top' => 'superior', 'right' => 'derecho', 'bottom' => 'inferior', 'left' => 'izquierdo'][$parts[1]];
        }

        return [
            'display' => 'Disposición', 'flex_direction' => 'Dirección', 'flex_wrap' => 'Ajustar filas',
            'justify_content' => 'Distribuir contenido', 'align_items' => 'Alinear elementos', 'align_self' => 'Alineación propia',
            'gap' => 'Separación', 'row_gap' => 'Separación entre filas', 'column_gap' => 'Separación entre columnas',
            'width' => 'Anchura', 'height' => 'Altura', 'min_width' => 'Anchura mínima', 'max_width' => 'Anchura máxima',
            'min_height' => 'Altura mínima', 'max_height' => 'Altura máxima', 'padding' => 'Relleno', 'margin' => 'Margen',
            'padding_top' => 'Relleno superior', 'padding_right' => 'Relleno derecho', 'padding_bottom' => 'Relleno inferior', 'padding_left' => 'Relleno izquierdo',
            'margin_top' => 'Margen superior', 'margin_right' => 'Margen derecho', 'margin_bottom' => 'Margen inferior', 'margin_left' => 'Margen izquierdo',
            'font_size' => 'Tamaño de texto', 'font_family' => 'Familia tipográfica', 'font_weight' => 'Grosor de texto', 'font_style' => 'Estilo de texto',
            'line_height' => 'Interlineado', 'letter_spacing' => 'Espaciado de letras', 'text_align' => 'Alineación de texto', 'text_transform' => 'Mayúsculas y minúsculas',
            'text_decoration' => 'Decoración de texto', 'color' => 'Color de texto', 'background_color' => 'Color de fondo', 'background_image' => 'Degradados y capas',
            'border_color' => 'Color de borde', 'border_width' => 'Grosor de borde', 'border_style' => 'Estilo de borde', 'border_radius' => 'Radio de borde',
            'box_shadow' => 'Sombra', 'text_shadow' => 'Sombra de texto', 'opacity' => 'Opacidad', 'position' => 'Posicionamiento',
            'top' => 'Arriba', 'right' => 'Derecha', 'bottom' => 'Abajo', 'left' => 'Izquierda', 'order' => 'Orden', 'z_index' => 'Capa',
            'overflow' => 'Desbordamiento', 'object_fit' => 'Ajuste de imagen', 'object_position' => 'Posición de imagen', 'visibility' => 'Visibilidad',
            'grid_auto_flow' => 'Distribución de cuadrícula', 'grid_template_columns' => 'Columnas de cuadrícula', 'grid_template_rows' => 'Filas de cuadrícula',
            'grid_column' => 'Columna y expansión', 'grid_row' => 'Fila y expansión', 'transform' => 'Transformación', 'filter' => 'Efecto visual',
            'outline_color' => 'Color de contorno', 'outline_width' => 'Grosor de contorno', 'outline_offset' => 'Distancia del contorno', 'transition_duration' => 'Duración de transición',
        ][$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    private static function invalid(string $key): never
    {
        throw ValidationException::withMessages(['appearance.'.$key => 'Valor de apariencia no permitido: '.$key]);
    }
}
