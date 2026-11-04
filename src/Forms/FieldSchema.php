<?php

declare(strict_types=1);

namespace DbCms\Core\Forms;

/**
 * Immutable, renderer-agnostic description of a single form field.
 *
 * Produced by {@see SchemaFactory} from Eloquent models, plain arrays or JSON,
 * so the exact same view component can render a database-backed form, an
 * inline array defined in Blade, or a payload received over the wire.
 */
final class FieldSchema
{
    /**
     * @param  array<int, array{value: string, label: string}>  $options
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type = 'text',
        public readonly string $label = '',
        public readonly ?string $placeholder = null,
        public readonly bool $required = false,
        public readonly string $rules = '',
        public readonly array $options = [],
    ) {}

    public function label(): string
    {
        return $this->label !== '' ? $this->label : $this->key;
    }

    public function htmlId(string $prefix = 'field'): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $this->key) ?? $this->key;

        return trim($prefix.'-'.strtolower($slug), '-');
    }

    /**
     * Layout hint: wide controls span both grid columns.
     */
    public function isWide(): bool
    {
        return in_array($this->type, ['textarea', 'checkbox', 'radio', 'captcha'], true);
    }

    /**
     * @param  array{value?: mixed, label?: mixed}|mixed  $option
     * @return array{value: string, label: string}
     */
    public static function normalizeOption(mixed $option, string|int $key = ''): array
    {
        if (is_array($option)) {
            $value = (string) ($option['value'] ?? $key);
            $label = (string) ($option['label'] ?? $value);
        } elseif (is_int($key) || ctype_digit((string) $key)) {
            $value = (string) $option;
            $label = (string) $option;
        } else {
            $value = (string) $key;
            $label = (string) $option;
        }

        return ['value' => $value, 'label' => $label];
    }
}
