<?php

declare(strict_types=1);

namespace DbCms\Core\Forms;

/**
 * Built-in field type: the primitive HTML controls rendered by the shared
 * `cms-form.field` layout.
 *
 * A single parametrized class keeps the primitive types DRY while still
 * conforming to {@see Contracts\FieldType}, so the renderer and validator treat
 * them exactly like any third-party type (e.g. the Captcha field).
 */
final class CoreFieldType implements Contracts\FieldType
{
    public function __construct(
        private readonly string $key,
        private readonly string $baseRule = 'string',
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function available(): bool
    {
        return true;
    }

    public function stores(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(FieldSchema $field): array
    {
        $rules = [$field->required ? 'required' : 'nullable', $this->baseRule];

        // The schema may carry pipe-delimited rules (`max:255|unique:posts`).
        // They are split into individual rules: Laravel no longer explodes
        // pipes inside an array element.
        foreach (explode('|', trim($field->rules)) as $rule) {
            $rule = trim($rule);

            if ($rule !== '') {
                $rules[] = $rule;
            }
        }

        return [$field->key => $rules];
    }

    public function view(): string
    {
        return 'components.cms-form.field';
    }
}
