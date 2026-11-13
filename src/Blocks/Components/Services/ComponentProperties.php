<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Services\Content\SafeContentRenderer;
use Illuminate\Validation\ValidationException;

/** Resolves declared scalar fields only: no caller-selected JSON mutation paths. */
final class ComponentProperties
{
    public function __construct(private readonly BlockTree $tree, private readonly BlockRegistryInterface $blocks) {}

    /** @param list<array<string,mixed>> $sections
     * @return array<string,array{node_id:string,field:string,label:string,type:string,default:mixed}> */
    public function targets(array $sections): array
    {
        $targets = [];
        foreach ($sections as $section) {
            foreach ($this->tree->nodes($section) as $node) {
                $id = $node['id'] ?? null;
                $type = (string) ($node['type'] ?? 'text');
                if ((! is_string($id) && ! is_int($id)) || ! $this->blocks->isAvailable($type) || $type === 'component') {
                    continue;
                }
                $schema = $this->blocks->get($type)?->getEditorSchema() ?? [];
                foreach ($this->scalarFields($node, $schema) as $field => $definition) {
                    if (isset($targets[(string) $id.'::'.$field])) {
                        throw ValidationException::withMessages(['exposed_properties' => 'Los destinos de las propiedades necesitan IDs de bloque únicos.']);
                    }
                    $targets[(string) $id.'::'.$field] = ['node_id' => (string) $id, 'field' => $field, 'label' => (string) ($definition['label'] ?? $field).' · '.$type.' · '.$id, 'type' => (string) $definition['type'], 'default' => data_get($node, $field)];
                }
            }
        }

        return $targets;
    }

    /** @param array<string,mixed> $data
     * @param array<string,array<string,mixed>> $schema
     * @return array<string,array<string,mixed>> */
    private function scalarFields(array $data, array $schema, string $prefix = ''): array
    {
        $result = [];
        foreach ($schema as $key => $field) {
            // Structure, styling, references and identifiers cannot become instance properties.
            if (isset($field['options_source']) || preg_match('/(?:^|_)(?:id|type|style|appearance|class|css|layout|width|height|gap|padding|margin|border|animation|color|background|bg|align|columns|format|font|size|opacity|position|display|overflow|z_index)(?:_|$)/i', $key)) {
                continue;
            }
            $path = $prefix.$key;
            $kind = (string) ($field['type'] ?? 'text');
            if ($kind === 'repeater') {
                foreach ((array) ($data[$key] ?? []) as $index => $row) {
                    if (is_array($row)) {
                        $result += $this->scalarFields($row, (array) ($field['fields'] ?? []), $path.'.'.$index.'.');
                    }
                }
            } elseif ($kind === 'object' && is_array($data[$key] ?? null)) {
                $result += $this->scalarFields($data[$key], (array) ($field['fields'] ?? []), $path.'.');
            } elseif (in_array($kind, ['text', 'string', 'textarea', 'media', 'url', 'email', 'number', 'integer', 'boolean'], true)
                && array_key_exists($key, $data) && (is_scalar($data[$key]) || $data[$key] === null)) {
                if (in_array($kind, ['number', 'integer', 'boolean'], true) && ($field['exposable'] ?? false) !== true && ! in_array($key, ['value', 'price', 'rating', 'amount', 'metric_value'], true)) {
                    continue;
                }
                $result[$path] = $field;
            }
        }

        return $result;
    }

    /** @param array<string,array<string,mixed>> $properties
     * @param list<array<string,mixed>> $sections */
    public function validate(array $properties, array $sections): void
    {
        $targets = $this->targets($sections);
        foreach ($properties as $name => $property) {
            if (! is_string($name) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) || ! is_string($property['node_id'] ?? null) || ! is_string($property['field'] ?? null)
                || ! isset($targets[$property['node_id'].'::'.$property['field']])) {
                throw ValidationException::withMessages(['exposed_properties' => 'Cada propiedad debe apuntar a un campo escalar de contenido de un bloque disponible.']);
            }
        }
    }

    /** @param list<array<string,mixed>> $sections
     * @param array<string,array<string,mixed>> $properties
     * @param array<string,mixed> $overrides
     * @return list<array<string,mixed>> */
    public function apply(array $sections, array $properties, array $overrides): array
    {
        $this->validate($properties, $sections);
        $targets = $this->targets($sections);
        foreach ($overrides as $name => $value) {
            if (! isset($properties[$name]) || (! is_scalar($value) && $value !== null)) {
                throw ValidationException::withMessages(['overrides' => 'Solo se admiten valores escalares para propiedades expresamente expuestas.']);
            }
            $property = $properties[$name];
            $kind = $targets[$property['node_id'].'::'.$property['field']]['type'];
            if (is_string($value) && in_array($kind, ['url', 'media'], true) && ! (new SafeContentRenderer)->isSafeUrl($value, $kind === 'media')) {
                throw ValidationException::withMessages(['overrides' => 'La propiedad requiere una URL segura.']);
            }
            if ($value !== null && match ($kind) {
                'integer' => ! is_int($value), 'number' => ! is_int($value) && ! is_float($value), 'boolean' => ! is_bool($value), default => ! is_string($value),
            }) {
                throw ValidationException::withMessages(['overrides' => 'El valor no coincide con el tipo de la propiedad expuesta.']);
            }
        }
        $replace = function (array $node) use (&$replace, $properties, $overrides): array {
            foreach ($properties as $name => $property) {
                if ((string) ($node['id'] ?? '') === $property['node_id'] && array_key_exists($name, $overrides)) {
                    data_set($node, $property['field'], $overrides[$name]);
                }
            }
            if (is_array($node['columns'] ?? null)) {
                foreach ($node['columns'] as &$column) {
                    $column['blocks'] = array_map($replace, (array) ($column['blocks'] ?? []));
                }
                unset($column);
            }

            return $this->tree->mapSlots($node, fn (array $nodes): array => array_map($replace, $nodes));
        };

        return array_map($replace, $sections);
    }
}
