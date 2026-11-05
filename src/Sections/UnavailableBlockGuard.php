<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

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
    public function reconcile(array $incoming, array $stored = []): array
    {
        $incoming = $this->clean($incoming);
        $stored = $this->clean($stored);
        $columns = SectionLayout::rawColumns($incoming);
        $oldColumns = SectionLayout::rawColumns($stored);
        $oldType = (string) ($stored['type'] ?? 'text');
        if ($stored !== [] && $oldColumns === [] && ! $this->blocks->isAvailable($oldType)) {
            if ($incoming !== $stored) {
                $this->invalid($oldType);
            }

            return $stored;
        }
        SectionAppearance::validate($incoming);
        if ($columns === [] && $oldColumns === []) {
            $type = (string) ($incoming['type'] ?? 'text');
            $this->blocks->validate($type, $incoming);

            return $incoming;
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
                $child = $this->reconcile($child, $this->takeMatch($oldChildren, $child, $j));
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
        foreach (SectionLayout::blocks($payload) as $block) {
            if (SectionLayout::rawColumns($block) !== []) {
                if ($this->containsUnavailable($block)) {
                    return true;
                }

                continue;
            }
            if (! $this->blocks->isAvailable((string) ($block['type'] ?? 'text'))) {
                return true;
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
