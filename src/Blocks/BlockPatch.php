<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Sections\UnavailableBlockGuard;
use Illuminate\Validation\ValidationException;

/** Stable-node operations on one locale; persistence still uses the entity writer. */
final class BlockPatch
{
    public function __construct(private readonly BlockTree $tree) {}

    /** @param list<array<string,mixed>> $nodes
     * @param list<array<string,mixed>> $operations
     * @return list<array<string,mixed>> */
    public function apply(array $nodes, array $operations): array
    {
        if (count($operations) > 100) {
            $this->invalid('Demasiadas operaciones en una edición.');
        }
        foreach ($operations as $operation) {
            $op = $operation['op'] ?? '';
            if (! in_array($op, ['update', 'insert', 'remove', 'move'], true)) {
                $this->invalid('Operación de bloque desconocida.');
            }
            $node = null;
            if ($op !== 'insert') {
                $path = $this->find($nodes, (string) ($operation['node_id'] ?? ''));
                if ($path === null) {
                    $this->invalid('El bloque solicitado ya no existe.');
                }
                $node = data_get($nodes, implode('.', $path));
                if (app(UnavailableBlockGuard::class)->containsUnavailable($node)
                    && ($op !== 'update' || ! app(BlockRegistryInterface::class)->isAvailable((string) ($node['type'] ?? 'text')))) {
                    $this->invalid('Reactiva los bloques no disponibles antes de moverlos o eliminarlos.');
                }
                if ($op === 'update') {
                    $values = $operation['values'] ?? null;
                    if (! is_array($values) || array_intersect(['id', 'type'], array_keys($values)) !== []) {
                        $this->invalid('No se puede cambiar la identidad del bloque.');
                    }
                    data_set($nodes, implode('.', $path), array_replace($node, $values));

                    continue;
                }
                $listPath = array_slice($path, 0, -1);
                $list = $listPath === [] ? $nodes : data_get($nodes, implode('.', $listPath));
                array_splice($list, (int) $path[count($path) - 1], 1);
                if ($listPath === []) {
                    $nodes = $list;
                } else {
                    data_set($nodes, implode('.', $listPath), $list);
                }
                if ($op === 'remove') {
                    continue;
                }
            }
            $node = $op === 'insert' ? ($operation['node'] ?? null) : $node;
            if (! is_array($node)) {
                $this->invalid('La inserción requiere un bloque.');
            }
            $parentId = $operation['parent_id'] ?? null;
            $listPath = [];
            if ($parentId !== null) {
                $parentPath = $this->find($nodes, (string) $parentId);
                if ($parentPath === null) {
                    $this->invalid('El destino no existe o forma un ciclo.');
                }
                $parent = data_get($nodes, implode('.', $parentPath));
                $slotPath = (string) ($operation['slot'] ?? 'children');
                $valid = false;
                foreach ($this->tree->slots($parent) as $slot) {
                    if (implode('.', $slot['path']) === $slotPath) {
                        $valid = true;
                    }
                }
                if (! $valid) {
                    $this->invalid('El destino no es una zona de bloques declarada.');
                }
                $listPath = [...$parentPath, ...explode('.', $slotPath)];
            }
            $list = $listPath === [] ? $nodes : (array) data_get($nodes, implode('.', $listPath), []);
            $index = isset($operation['index']) ? (int) $operation['index'] : count($list);
            if ($index < 0 || $index > count($list)) {
                $this->invalid('Posición de inserción inválida.');
            }
            array_splice($list, $index, 0, [$node]);
            if ($listPath === []) {
                $nodes = $list;
            } else {
                data_set($nodes, implode('.', $listPath), $list);
            }
        }
        $this->tree->assertLimits($nodes);
        $this->tree->assertUniqueIds($nodes);

        return $nodes;
    }

    /** @param list<array<string,mixed>> $nodes
     * @param list<string|int> $prefix
     * @return list<string|int>|null */
    private function find(array $nodes, string $id, array $prefix = []): ?array
    {
        foreach ($nodes as $index => $node) {
            $path = [...$prefix, $index];
            if ($id !== '' && (string) ($node['id'] ?? '') === $id) {
                return $path;
            }
            foreach ($this->tree->slots($node) as $slot) {
                $found = $this->find($slot['nodes'], $id, [...$path, ...$slot['path']]);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($prefix !== [] && ! app(BlockRegistryInterface::class)->isAvailable((string) ($node['type'] ?? 'text'))) {
                continue;
            }
            foreach ((array) ($node['columns'] ?? []) as $columnIndex => $column) {
                if (is_array($column)) {
                    $children = [];
                    foreach ((array) ($column['blocks'] ?? []) as $child) {
                        if (! is_array($child)) {
                            $this->invalid('La columna contiene un bloque inválido.');
                        }
                        $children[] = $child;
                    }
                    $found = $this->find($children, $id, [...$path, 'columns', $columnIndex, 'blocks']);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['operations' => $message]);
    }
}
