<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use Closure;
use DbCms\Core\Blocks\Contracts\BlockPayloadCompatibilityInterface;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Traverses only block slots declared by their owner, plus canonical section columns. */
final class BlockTree
{
    public function __construct(private readonly BlockRegistryInterface $registry) {}

    /** @param array<string,mixed> $payload */
    public function supports(array $payload): bool
    {
        $definition = $this->registry->get((string) ($payload['type'] ?? 'text'));

        return ! $definition instanceof BlockPayloadCompatibilityInterface
            || array_intersect($definition->retiredPayloadKeys(), array_keys($payload)) === [];
    }

    /** @param array<mixed> $payload */
    public function assertLimits(array $payload): void
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > (int) config('cms.blocks.max_payload_bytes', 1048576)) {
            $this->invalid('El contenido supera el tamaño máximo permitido.');
        }
        $nodes = 0;
        $inspect = function (array $value, int $depth) use (&$inspect, &$nodes): void {
            if ($depth > 64) {
                $this->invalid('La estructura del contenido es demasiado profunda.');
            }
            if (isset($value['type']) && ++$nodes > (int) config('cms.blocks.max_nodes', 1000)) {
                $this->invalid('La composición contiene demasiados elementos.');
            }
            foreach ($value as $item) {
                if (is_array($item)) {
                    $inspect($item, $depth + 1);
                }
            }
        };
        $inspect($payload, 0);
    }

    /** @param array<string,mixed> $node
     * @return list<array<string,mixed>> */
    public function nodes(array $node, int $depth = 0): array
    {
        $walk = fn (): array => $this->walk($node, $depth);

        return $this->registry instanceof BlockRegistry ? $this->registry->withinAvailability($walk) : $walk();
    }

    /** @param array<string,mixed> $node
     * @return list<array<string,mixed>> */
    private function walk(array $node, int $depth): array
    {
        if ($depth > (int) config('cms.blocks.max_depth', 16)) {
            $this->invalid('La composición supera la profundidad de bloques permitida.');
        }
        $result = [];
        $type = (string) ($node['type'] ?? 'text');
        if (! $this->supports($node) || ($depth > 0 && ! $this->registry->isAvailable($type))) {
            return [$node];
        }
        $columns = $node['columns'] ?? [];
        if (is_array($columns) && $columns !== []) {
            foreach ($columns as $column) {
                if (! is_array($column)) {
                    $this->invalid('Cada columna debe ser un objeto.');
                }
                foreach ((array) ($column['blocks'] ?? []) as $child) {
                    if (! is_array($child)) {
                        $this->invalid('Cada bloque debe ser un objeto.');
                    }
                    array_push($result, ...$this->walk($child, $depth + 1));
                }
            }

            return $result;
        }
        $result[] = $node;
        // An unavailable owner is opaque: never invoke its editor data or descendants.
        if (! $this->registry->isAvailable((string) ($node['type'] ?? 'text'))) {
            return $result;
        }
        foreach ($this->slots($node) as $slot) {
            foreach ($slot['nodes'] as $child) {
                array_push($result, ...$this->walk($child, $depth + 1));
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $node
     * @return list<array{path:list<string|int>,nodes:list<array<string,mixed>>}> */
    public function slots(array $node): array
    {
        if (! $this->supports($node) || ! $this->registry->isAvailable((string) ($node['type'] ?? 'text'))) {
            return [];
        }
        $schema = $this->registry->get((string) ($node['type'] ?? 'text'))?->getEditorSchema() ?? [];

        return $this->fieldSlots($node, $schema);
    }

    /** @param array<string,mixed> $data
     * @param array<string,array<string,mixed>> $schema
     * @param list<string|int> $prefix
     * @return list<array{path:list<string|int>,nodes:list<array<string,mixed>>}> */
    private function fieldSlots(array $data, array $schema, array $prefix = []): array
    {
        $result = [];
        foreach ($schema as $key => $field) {
            $path = [...$prefix, $key];
            if (($field['type'] ?? null) === 'blocks') {
                $value = $data[$key] ?? [];
                if (! is_array($value) || ! array_is_list($value) || array_filter($value, static fn (mixed $item): bool => ! is_array($item)) !== []) {
                    $this->invalid('La zona '.implode('.', $path).' debe contener una lista de bloques.');
                }
                $result[] = ['path' => $path, 'nodes' => $value];
            } elseif (($field['type'] ?? null) === 'repeater') {
                foreach ((array) ($data[$key] ?? []) as $index => $row) {
                    if (is_array($row)) {
                        array_push($result, ...$this->fieldSlots($row, (array) ($field['fields'] ?? []), [...$path, $index]));
                    }
                }
            } elseif (($field['type'] ?? null) === 'object' && is_array($data[$key] ?? null)) {
                array_push($result, ...$this->fieldSlots($data[$key], (array) ($field['fields'] ?? []), $path));
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $node
     * @param Closure(list<array<string,mixed>>,list<string|int>):list<array<string,mixed>> $map
     * @return array<string,mixed> */
    public function mapSlots(array $node, Closure $map): array
    {
        foreach ($this->slots($node) as $slot) {
            data_set($node, implode('.', $slot['path']), $map($slot['nodes'], $slot['path']));
        }

        return $node;
    }

    /** @param array<string,mixed> $node
     * @return array<string,mixed> */
    public function identify(array $node): array
    {
        if (! $this->supports($node) || ! $this->registry->isAvailable((string) ($node['type'] ?? 'text'))) {
            return $node;
        }
        $node['id'] ??= 'node_'.Str::uuid();
        foreach ($this->slots($node) as $slot) {
            $path = $slot['path'];
            if (count($path) > 1 && is_int($path[count($path) - 2])) {
                $row = implode('.', array_slice($path, 0, -1));
                if (data_get($node, $row.'.id') === null) {
                    data_set($node, $row.'.id', 'item_'.Str::uuid());
                }
            }
            data_set($node, implode('.', $path), array_map($this->identify(...), $slot['nodes']));
        }

        return $node;
    }

    /** @param list<array<string,mixed>> $roots */
    public function assertUniqueIds(array $roots): void
    {
        $seen = [];
        foreach ($roots as $root) {
            if (! is_array($root)) {
                $this->invalid('Cada sección debe ser un objeto de bloque.');
            }
            foreach ($this->nodes($root) as $node) {
                $id = $node['id'] ?? null;
                if ($id === null) {
                    continue;
                }
                if ((! is_string($id) && ! is_int($id)) || preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', (string) $id) !== 1 || isset($seen[(string) $id])) {
                    $this->invalid('Cada bloque debe tener una identidad válida y única dentro del idioma.');
                }
                $seen[(string) $id] = true;
            }
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['sections_json' => $message]);
    }
}
