<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use Illuminate\Validation\ValidationException;

/** Preserves stored unavailable nodes and rejects insertion or modification. */
final class UnavailableBlockGuard
{
    public function __construct(private readonly BlockRegistryInterface $blocks) {}

    /**
     * @param  array<string,mixed>  $incoming
     * @param  array<string,mixed>  $stored
     * @return array<string,mixed>
     */
    public function reconcile(array $incoming, array $stored = [], bool $nested = false): array
    {
        $incoming = $this->clean($incoming);
        $stored = $this->clean($stored);
        $columns = SectionLayout::rawColumns($incoming);
        $oldColumns = SectionLayout::rawColumns($stored);
        $oldType = (string) ($stored['type'] ?? 'text');
        if ($stored !== [] && (! app(BlockTree::class)->supports($stored) || (($nested || $oldColumns === []) && ! $this->blocks->isAvailable($oldType)))) {
            if ($incoming !== $stored) {
                $this->invalid($oldType);
            }

            return $stored;
        }
        if (! app(BlockTree::class)->supports($incoming) || ($nested && ! $this->blocks->isAvailable((string) ($incoming['type'] ?? 'text')))) {
            $this->invalid((string) ($incoming['type'] ?? 'text'));
        }
        SectionAppearance::validate($incoming);
        if ($columns === [] && $oldColumns === []) {
            $type = (string) ($incoming['type'] ?? 'text');
            if ($this->blocks->isAvailable($type)) {
                $incoming = $this->reconcileFields($this->blocks->get($type)?->getEditorSchema() ?? [], $incoming, $stored);
            }
            $this->blocks->validate($type, $incoming);

            return app(BlockTree::class)->identify($incoming);
        }
        foreach ($columns as $i => &$column) {
            SectionAppearance::validate((array) ($column['style'] ?? []));
            $old = $this->takeMatch($oldColumns, $column, $i);
            $children = (array) ($column['blocks'] ?? []);
            $oldChildren = (array) ($old['blocks'] ?? []);
            foreach ($children as $j => &$child) {
                if (! is_array($child)) {
                    $this->invalid('invalid');
                }
                $child = $this->reconcile($child, $this->takeMatch($oldChildren, $child, $j), true);
            }
            unset($child);
            foreach ($oldChildren as $j => $oldChild) {
                if (is_array($oldChild) && $this->containsUnavailable($oldChild)) {
                    array_splice($children, min($j, count($children)), 0, [$oldChild]);
                }
            }
            $column['blocks'] = $children;
        }
        unset($column);
        foreach ($oldColumns as $i => $oldColumn) {
            if (is_array($oldColumn)) {
                $preserved = array_values(array_filter((array) ($oldColumn['blocks'] ?? []), fn (mixed $block): bool => is_array($block) && $this->containsUnavailable($block)));
                if ($preserved !== []) {
                    $oldColumn['blocks'] = $preserved;
                    $columns[] = $oldColumn;
                }
            }
        }
        $incoming['columns'] = $columns;

        return $incoming;
    }

    /**
     * @param array<string,mixed> $payload */
    public function containsUnavailable(array $payload): bool
    {
        if (! app(BlockTree::class)->supports($payload) || ! $this->blocks->isAvailable((string) ($payload['type'] ?? 'text'))) {
            return true;
        }
        foreach (SectionLayout::blocks($payload) as $block) {
            if (SectionLayout::rawColumns($block) !== []) {
                if ($this->containsUnavailable($block)) {
                    return true;
                }

                continue;
            }
            if (! app(BlockTree::class)->supports($block) || ! $this->blocks->isAvailable((string) ($block['type'] ?? 'text'))) {
                return true;
            }
            foreach (app(BlockTree::class)->slots($block) as $slot) {
                foreach ($slot['nodes'] as $child) {
                    if ($this->containsUnavailable($child)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @param array<string,array<string,mixed>> $schema
     * @param array<string,mixed> $incoming
     * @param array<string,mixed> $stored
     * @return array<string,mixed> */
    private function reconcileFields(array $schema, array $incoming, array $stored): array
    {
        foreach ($schema as $key => $field) {
            $type = $field['type'] ?? null;
            if ($type === 'blocks') {
                $children = $incoming[$key] ?? [];
                if (! is_array($children) || ! array_is_list($children)) {
                    $this->invalid('invalid slot');
                }
                $old = (array) ($stored[$key] ?? []);
                foreach ($children as $index => &$child) {
                    if (! is_array($child)) {
                        $this->invalid('invalid child');
                    }
                    $child = $this->reconcile($child, $this->takeMatch($old, $child, $index), true);
                }
                unset($child);
                foreach ($old as $index => $child) {
                    if (is_array($child) && $this->containsUnavailable($child)) {
                        array_splice($children, min((int) $index, count($children)), 0, [$child]);
                    }
                }
                $incoming[$key] = $children;
            } elseif ($type === 'repeater') {
                $rows = (array) ($incoming[$key] ?? []);
                $old = (array) ($stored[$key] ?? []);
                foreach ($rows as $index => &$row) {
                    if (is_array($row)) {
                        $row = $this->reconcileFields((array) ($field['fields'] ?? []), $row, $this->takeMatch($old, $row, $index));
                    }
                }
                unset($row);
                foreach ($old as $index => $row) {
                    if (is_array($row) && $this->unavailableFields((array) ($field['fields'] ?? []), $row)) {
                        array_splice($rows, min((int) $index, count($rows)), 0, [$row]);
                    }
                }
                $incoming[$key] = $rows;
            } elseif ($type === 'object') {
                $incoming[$key] = $this->reconcileFields((array) ($field['fields'] ?? []), (array) ($incoming[$key] ?? []), (array) ($stored[$key] ?? []));
            }
        }

        return $incoming;
    }

    /** @param array<string,array<string,mixed>> $schema
     * @param array<string,mixed> $payload */
    private function unavailableFields(array $schema, array $payload): bool
    {
        foreach ($schema as $key => $field) {
            foreach ((array) ($payload[$key] ?? []) as $child) {
                if (! is_array($child)) {
                    continue;
                }
                if (($field['type'] ?? null) === 'blocks' && $this->containsUnavailable($child)) {
                    return true;
                }
                if (($field['type'] ?? null) === 'repeater' && $this->unavailableFields((array) ($field['fields'] ?? []), $child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $items
     * @param array<string,mixed> $target
     * @return array<string,mixed> */
    private function takeMatch(array &$items, array $target, int $index): array
    {
        if (isset($target['id'])) {
            foreach ($items as $key => $item) {
                if (is_array($item) && ($item['id'] ?? null) === $target['id']) {
                    unset($items[$key]);

                    return $item;
                }
            }

            return [];
        }

        foreach ($items as $key => $item) {
            if (is_array($item) && $this->clean($item) === $this->clean($target)) {
                unset($items[$key]);

                return $item;
            }
        }
        $item = $items[$index] ?? null;
        if (is_array($item) && ! isset($item['id']) && ($item['type'] ?? 'text') === ($target['type'] ?? 'text')) {
            unset($items[$index]);

            return $item;
        }

        return [];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed> */
    private function clean(array $payload): array
    {
        unset($payload['is_collapsed']);
        foreach (array_keys($payload) as $key) {
            if (str_starts_with((string) $key, '_')) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    private function invalid(string $type): never
    {
        throw ValidationException::withMessages(['sections_json' => 'An unavailable block cannot be changed: '.$type]);
    }
}
