<?php

declare(strict_types=1);

namespace DbCms\Core\Forms;

/**
 * Reusable field schemas declared in code.
 *
 * The blog comment form is not a database-backed Forms definition, but it is
 * rendered and validated through the exact same framework: one schema feeds
 * `<x-cms-form.fields>` in the theme and the validation rules in the controller,
 * so view and validation can never drift apart.
 */
final class FormSchemas
{
    public function __construct(
        private readonly FieldTypeRegistry $registry,
    ) {}

    /**
     * Blog post comment schema (author, email, content + captcha when available).
     *
     * @param  array<string, string>  $labels  field key => label override (themes keep their own copy)
     * @return array<int, FieldSchema>
     */
    public function blogComment(array $labels = []): array
    {
        $labels = array_merge([
            'author_name' => (string) __('Nombre *'),
            'author_email' => (string) __('Email *'),
            'content' => (string) __('Comentario *'),
            'captcha' => (string) __('Verificación de seguridad'),
        ], $labels);

        $fields = [
            new FieldSchema(
                key: 'author_name',
                type: 'text',
                label: $labels['author_name'],
                required: true,
                rules: 'max:100',
            ),
            new FieldSchema(
                key: 'author_email',
                type: 'email',
                label: $labels['author_email'],
                required: true,
                rules: 'max:150',
            ),
            new FieldSchema(
                key: 'content',
                type: 'textarea',
                label: $labels['content'],
                required: true,
                rules: 'min:3|max:3000',
            ),
        ];

        // Anti-spam field: only when the Captcha module is active.
        $captcha = $this->registry->get('captcha');

        if ($captcha !== null && $captcha->available()) {
            $fields[] = new FieldSchema(
                key: 'captcha',
                type: 'captcha',
                label: $labels['captcha'],
                required: true,
            );
        }

        return $fields;
    }

    /**
     * Compile the validation rules of a schema through the field registry.
     *
     * @param  array<int, FieldSchema>  $schema
     * @return array<string, array<int, mixed>>
     */
    public function compileRules(array $schema): array
    {
        $rules = [];

        foreach ($schema as $field) {
            $type = $this->registry->resolve($field->type);

            if ($type === null) {
                continue;
            }

            foreach ($type->rules($field) as $input => $inputRules) {
                $rules[$input] = $inputRules;
            }
        }

        return $rules;
    }
}
