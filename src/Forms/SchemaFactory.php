<?php

declare(strict_types=1);

namespace DbCms\Core\Forms;

/**
 * Normalizes any supported source into a list of {@see FieldSchema}.
 *
 * The three entry points produce the exact same structure so that a form loaded
 * from the database, an inline array written in Blade, or a JSON payload shared
 * by an API/MCP client all render and validate identically:
 *
 *     $factory->fromModels($form->fields);   // Eloquent collection
 *     $factory->fromArray([...]);            // Blade / PHP
 *     $factory->fromJson($json);             // API / MCP / front-end
 */
final class SchemaFactory
{
    /**
     * @param  iterable<int|string, mixed>  $fields
     * @return array<int, FieldSchema>
     */
    public function fromModels(iterable $fields): array
    {
        return $this->normalize($fields);
    }

    /**
     * @param  array<int|string, mixed>  $fields
     * @return array<int, FieldSchema>
     */
    public function fromArray(array $fields): array
    {
        if (isset($fields['fields']) && is_array($fields['fields'])) {
            $fields = $fields['fields'];
        }

        return $this->normalize($fields);
    }

    /**
     * @return array<int, FieldSchema>
     */
    public function fromJson(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<int|string, mixed> $decoded */
        return $this->fromArray($decoded);
    }

    /**
     * @param  iterable<int|string, mixed>  $fields
     * @return array<int, FieldSchema>
     */
    private function normalize(iterable $fields): array
    {
        $schema = [];

        foreach ($fields as $field) {
            $key = trim((string) $this->value($field, ['field_key', 'key', 'name'], ''));

            if ($key === '') {
                continue;
            }

            $type = strtolower(trim((string) $this->value($field, ['type'], 'text')));

            $schema[] = new FieldSchema(
                key: $key,
                type: $type !== '' ? $type : 'text',
                label: (string) $this->value($field, ['label', 'title'], ''),
                placeholder: $this->nullableString($this->value($field, ['placeholder'], null)),
                required: (bool) $this->value($field, ['is_required', 'required'], false),
                rules: (string) $this->value($field, ['validation_rules', 'rules'], ''),
                options: $this->options($field),
            );
        }

        return $schema;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function value(mixed $source, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($source, $key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function options(mixed $field): array
    {
        $raw = data_get($field, 'options');

        if (is_string($raw)) {
            return $this->parseOptionLines($raw);
        }

        if (! is_array($raw)) {
            return [];
        }

        $pairs = [];

        foreach ($raw as $key => $option) {
            $pairs[] = FieldSchema::normalizeOption($option, $key);
        }

        return $pairs;
    }

    /**
     * Parse the builder's free-text format: one `value | Label` per line.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function parseOptionLines(string $raw): array
    {
        $pairs = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_contains($line, '|')) {
                [$value, $label] = array_map('trim', explode('|', $line, 2));
                $pairs[] = ['value' => $value, 'label' => $label];

                continue;
            }

            $pairs[] = ['value' => $line, 'label' => $line];
        }

        return $pairs;
    }
}
