<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;

final class RichTextSectionUpgrade
{
    public function __construct(private readonly BlockTree $tree, private readonly BlockRegistryInterface $blocks) {}

    /** @param array{attributes: array<string, mixed>, translations: list<array<string, mixed>>} $record
     * @return array{attributes: array<string, mixed>, translations: list<array<string, mixed>>} */
    public function record(array $record): array
    {
        $attributes = $record['attributes'];
        $master = $this->decode($attributes['styling_json'] ?? null);
        $translations = array_map(fn (array $row): array => $this->decode($row['content_json'] ?? null), $record['translations']);
        $hasColumns = array_any([$master, ...$translations], static fn (array $payload): bool => SectionLayout::rawColumns($payload) !== []);

        if (! $hasColumns && ($attributes['type'] ?? null) === SectionLayout::CONTAINER_TYPE) {
            $record['attributes']['type'] = 'rich_text';
        }
        $next = $this->node($master, $hasColumns);
        if ($next !== $master) {
            $record['attributes']['styling_json'] = $this->encode($next);
        }
        foreach ($translations as $index => $payload) {
            $next = $this->node($payload, $hasColumns && ! is_string($payload['content'] ?? null));
            if ($next !== $payload) {
                $record['translations'][$index]['content_json'] = $this->encode($next);
            }
        }

        return $record;
    }

    /** @param array<string, mixed> $node
     * @return array<string, mixed> */
    private function node(array $node, bool $section = false): array
    {
        $columns = SectionLayout::rawColumns($node);
        if ($columns !== []) {
            foreach ($columns as &$column) {
                if (! is_array($column) || ! is_array($column['blocks'] ?? [])) {
                    throw new \RuntimeException('Cannot upgrade malformed section columns.');
                }
                if (isset($column['blocks'])) {
                    $column['blocks'] = array_map($this->node(...), $column['blocks']);
                }
            }
            unset($column);
            $node['columns'] = array_is_list($node['columns']) ? $columns : $columns[0];

            return $node;
        }

        $type = $node['type'] ?? null;
        if ($type === SectionLayout::CONTAINER_TYPE && ! $section) {
            $node['type'] = 'rich_text';

            return $node;
        }
        if (! is_string($type) || $type === SectionLayout::CONTAINER_TYPE) {
            return $node;
        }

        if (! $this->tree->supports($node) || $this->blocks->get($type) === null) {
            if ($this->hasLegacyBlock($node)) {
                throw new \RuntimeException('Cannot upgrade nested text blocks without the schema for '.$type.'.');
            }

            return $node;
        }

        foreach ($this->tree->slots($node, includeInactive: true) as $slot) {
            $children = array_map($this->node(...), $slot['nodes']);
            if ($children !== $slot['nodes']) {
                data_set($node, implode('.', $slot['path']), $children);
            }
        }

        return $node;
    }

    /** @param array<array-key, mixed> $value */
    private function hasLegacyBlock(array $value): bool
    {
        return ($value['type'] ?? null) === SectionLayout::CONTAINER_TYPE
            || array_any($value, fn (mixed $child): bool => is_array($child) && $this->hasLegacyBlock($child));
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        $payload = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
        if (! is_array($payload)) {
            throw new \RuntimeException('Cannot upgrade malformed section content.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
