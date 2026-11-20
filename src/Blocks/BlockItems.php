<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use Illuminate\Validation\ValidationException;

/** Resolves only named, schema-declared keyed collections within one block. */
final class BlockItems
{
    /** @param array<string,mixed> $node
     * @param  array<string,array<string,mixed>>  $schema
     * @return array{0:list<string|int>,1:array<string,mixed>,2:array<string,array<string,mixed>>}
     */
    public static function resolve(array $node, array $schema, mixed $target): array
    {
        if (! is_array($target) || ! array_is_list($target) || count($target) > 8) {
            self::invalid('target', 'La ruta debe ser una lista de colecciones e identificadores.');
        }
        $path = [];
        foreach ($target as $segment) {
            if (! is_array($segment) || ! is_string($segment['collection'] ?? null) || ! is_string($segment['id'] ?? null)
                || ! self::isKeyed($schema[$segment['collection']] ?? [])) {
                self::invalid('target', 'La colección no está disponible en este destino.');
            }
            $key = $segment['collection'];
            $items = (array) ($node[$key] ?? []);
            $i = self::position($items, $segment['id'], 'target');
            $path = [...$path, $key, $i];
            $node = $items[$i];
            $schema = $schema[$key]['fields'];
        }

        return [$path, $node, $schema];
    }

    /** @param array<string,mixed> $node
     * @param  array<string,array<string,mixed>>  $schema
     * @return array<string,mixed>
     */
    public static function summary(array $node, array $schema): array
    {
        $result = array_intersect_key($node, array_flip(['id', 'type', 'schema_version', 'title', 'name', 'layout', 'kind', 'at', 'from_scene', 'through_scene']));
        foreach ($schema as $key => $field) {
            if (self::isKeyed($field)) {
                $result[$key] = array_values(array_map(static fn (array $row): array => self::summary($row, $field['fields']), (array) ($node[$key] ?? [])));
            } elseif (($field['type'] ?? null) === 'blocks') {
                $result[$key] = array_values(array_map(static fn (array $block): array => array_intersect_key($block, array_flip(['id', 'type'])), (array) ($node[$key] ?? [])));
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $node
     * @param  array<string,array<string,mixed>>  $schema
     * @param  array<string,mixed>  $operation
     * @return array<string,mixed>
     */
    public static function apply(array $node, array $schema, array $operation): array
    {
        $op = $operation['op'];
        $target = $operation['target'] ?? [];
        [$path, $item, $fields] = self::resolve($node, $schema, $target);
        if ($op === 'update_item') {
            $values = $operation['values'] ?? null;
            if ($path === [] || ! is_array($values) || $values === [] || array_is_list($values)) {
                self::invalid('values', 'Selecciona un elemento y facilita los campos que quieres editar.');
            }
            foreach (array_keys($values) as $field) {
                if ($field === 'id' || ! isset($fields[$field]) || in_array($fields[$field]['type'] ?? '', ['blocks', 'repeater'], true)) {
                    self::invalid('values.'.$field, 'Este campo necesita su propia operación.');
                }
            }
            data_set($node, implode('.', $path), array_replace($item, $values));

            return $node;
        }

        if ($op === 'insert_item') {
            $key = $operation['collection'] ?? '';
            $new = $operation['item'] ?? null;
            if (! is_string($key) || ! self::isKeyed($fields[$key] ?? []) || ! is_array($new)
                || ! is_string($new['id'] ?? null) || preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $new['id']) !== 1) {
                self::invalid('item', 'Selecciona una colección editable y proporciona un elemento con ID.');
            }
            $items = (array) ($item[$key] ?? []);
            foreach ($items as $row) {
                if (($row['id'] ?? null) === $new['id']) {
                    self::invalid('item.id', 'El identificador ya está en la colección.');
                }
            }
            $listPath = [...$path, $key];
        } else {
            if ($path === []) {
                self::invalid('target', 'Selecciona el elemento que quieres mover o eliminar.');
            }
            $listPath = array_slice($path, 0, -1);
            $items = (array) data_get($node, implode('.', $listPath));
            $index = (int) $path[count($path) - 1];
            $new = $items[$index];
            if ($op === 'move_item' && ($operation['before_id'] ?? null) === ($new['id'] ?? null)) {
                return $node;
            }
            array_splice($items, $index, 1);
        }

        if ($op !== 'remove_item') {
            $before = $operation['before_id'] ?? null;
            if ($before !== null && ! is_string($before)) {
                self::invalid('before_id', 'La posición de destino requiere un ID.');
            }
            $index = $before === null ? count($items) : self::position($items, $before, 'before_id');
            array_splice($items, $index, 0, [$new]);
        }
        data_set($node, implode('.', $listPath), $items);

        return $node;
    }

    /** @param array<string,mixed> $field */
    private static function isKeyed(array $field): bool
    {
        return ($field['type'] ?? null) === 'repeater' && ($field['keyed'] ?? false) === true;
    }

    /** @param array<mixed> $rows */
    private static function position(array $rows, string $id, string $field): int
    {
        foreach ($rows as $index => $row) {
            if (($row['id'] ?? null) === $id) {
                return (int) $index;
            }
        }
        self::invalid($field, 'El elemento o destino ya no está en la colección.');
    }

    private static function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
