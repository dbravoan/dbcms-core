<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Models\Language;

/**
 * The canonical contract of a block-manager section.
 *
 * A **section** is a container (column layout + row styling + animation); inside
 * it live 1..N **columns**, and inside each column live 0..N **blocks** — and a
 * block is exactly what the block registry offers (text, hero, features… or a
 * module widget: blog, shop, gallery, form, charts). Nothing in a section knows
 * which module produced a block, which is what keeps the editor, the renderer,
 * the MCP surface and the AI assistant working on the very same shape.
 *
 * ```
 * section
 *  ├── columns: [ { id, blocks: [ {…block payload…} ] } ]   ← canonical key
 *  └── one flat key per column, each with its own blocks
 * ```
 *
 * Content resolution is **single-sourced** here: for every field, the localized
 * `content_json` wins over the `styling_json` master. Before this class, the
 * master (usually Spanish) won, which is why an English page rendered Spanish
 * text inside multi-column sections.
 *
 * The class never mutates the database: it is a pure normalizer shared by the
 * renderer, the editor payload, the sync, MCP, the AI bridge and the seeders.
 */
final class SectionLayout
{
    /** Canonical key holding the columns of a section. */
    public const COLUMNS = 'columns';

    /** Container key a block uses for its own interior CSS grid (contenedores y características). */
    public const GRID_COLUMNS = 'grid_columns';

    /**
     * Container CSS grid of a block: the canonical `grid_columns` key.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function gridColumns(array $payload, string $default = '3'): string
    {
        $value = $payload[self::GRID_COLUMNS] ?? null;

        if (is_numeric($value) && (int) $value >= 1) {
            return (string) (int) $value;
        }

        return $default;
    }

    /**
     * Normalizes a section payload into the canonical shape:
     *
     * - `columns` is always a list of `{id, blocks, style}`.
     * - A section with content but no columns becomes one column holding its own
     *   content, so every section renders through the same path.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalize(array $payload): array
    {
        $columns = self::rawColumns($payload);

        if ($columns !== []) {
            unset($payload[self::COLUMNS]);
            $payload[self::COLUMNS] = self::normalizeColumns($columns, $payload);

            return $payload;
        }

        if (array_key_exists(self::COLUMNS, $payload)) {
            unset($payload[self::COLUMNS]);

            if (self::hasContent($payload)) {
                // A section with content but no columns: its own content becomes
                // the first column, so every section renders through one path.
                $payload[self::COLUMNS] = [[
                    'id' => self::columnId(0),
                    'blocks' => [self::sectionAsBlock($payload)],
                ]];
            }
        }

        return $payload;
    }

    /**
     * Locale-aware payload: the localized content of the locale wins over the
     * master payload, field by field, for every level (section and blocks).
     *
     * @param  array<string, mixed>  $master  styling_json (defaults + row styling)
     * @param  array<string, mixed>  $content  content_json for one locale
     * @return array<string, mixed>
     */
    public static function resolve(array $master, array $content): array
    {
        $merged = array_replace($master, $content);

        // Columns are always normalized after merging: the master may carry the
        // layout and the locale the block content (or the other way around).
        return self::normalize($merged);
    }

    /**
     * The columns of a payload, normalized and guaranteed to be a list.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{id: string, blocks: list<array<string, mixed>>}>
     */
    public static function columns(array $payload): array
    {
        $columns = self::rawColumns($payload);

        if ($columns === []) {
            return [];
        }

        return self::normalizeColumns($columns, $payload, includeSelf: false);
    }

    /**
     * Every block of the section, flattened in visual order (columns left to
     * right, blocks top to bottom). When the section has no columns, the section
     * itself is its own single block.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function blocks(array $payload): array
    {
        $columns = self::columns($payload);

        if ($columns === []) {
            return [$payload];
        }

        $blocks = [];

        foreach ($columns as $column) {
            foreach ($column['blocks'] as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Plain text of every block (GEO/SEO surfaces, diffs, inspectors).
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    public static function plainText(array $payload, array $keys = ['title', 'headline', 'content', 'body', 'summary', 'subtitle']): string
    {
        $chunks = [];

        foreach (self::blocks($payload) as $block) {
            foreach ($keys as $key) {
                $value = $block[$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $chunks[] = trim(strip_tags($value));
                }
            }

            // Repeatable content (listas y características) lives in `items`.
            foreach ((array) ($block['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach ($keys as $key) {
                    $value = $item[$key] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        $chunks[] = trim(strip_tags($value));
                    }
                }
            }

        }

        return implode("\n\n", array_values(array_unique($chunks)));
    }

    /**
     * Active locales of the installation (editor payloads).
     *
     * @return list<string>
     */
    public static function activeLocales(): array
    {
        $locales = Language::query()->where('is_active', true)->orderBy('id')->pluck('code')->all();

        if ($locales === []) {
            return ['es'];
        }

        return array_values(array_map('strval', $locales));
    }

    /**
     * Raw column list of a payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    public static function rawColumns(array $payload): array
    {
        $candidate = $payload[self::COLUMNS] ?? null;

        if (! is_array($candidate) || $candidate === []) {
            return [];
        }

        // A list of columns is either numeric-keyed or holds `blocks`/`id` keys.
        if (array_is_list($candidate)) {
            return $candidate;
        }

        return array_key_exists('blocks', $candidate) ? [$candidate] : [];
    }

    /**
     * @param  array<mixed>  $columns
     * @param  array<string, mixed>  $payload
     * @return list<array{id: string, blocks: list<array<string, mixed>>}>
     */
    private static function normalizeColumns(array $columns, array $payload, bool $includeSelf = true): array
    {
        $normalized = [];

        foreach ($columns as $index => $column) {
            if (! is_array($column)) {
                continue;
            }

            $blocks = [];

            foreach ((array) ($column['blocks'] ?? []) as $block) {
                if (is_array($block)) {
                    $blocks[] = self::normalize($block);
                }
            }

            $normalized[] = [
                'id' => (string) ($column['id'] ?? self::columnId((int) $index)),
                'blocks' => $blocks,
                'width' => $column['width'] ?? null,
                // Column styling (background, borders, padding, bg behaviour):
                // preserved so the appearance modal's `Column` scope round-trips.
                'style' => is_array($column['style'] ?? null) ? $column['style'] : [],
            ];
        }

        // No columns declared: the section's own content IS the first column,
        // allowing standalone and column layouts to share the renderer.
        if ($normalized === [] && $includeSelf && self::hasContent($payload)) {
            $normalized[] = [
                'id' => self::columnId(0),
                'blocks' => [self::sectionAsBlock($payload)],
            ];
        }

        return $normalized;
    }

    /**
     * The section seen as a block, so it can live inside a column.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function sectionAsBlock(array $payload): array
    {
        $block = $payload;

        foreach ([self::COLUMNS, 'columns_layout', 'id'] as $containerKey) {
            unset($block[$containerKey]);
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function hasContent(array $payload): bool
    {
        foreach (['title', 'headline', 'content', 'body', 'summary', 'subtitle', 'items'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return true;
            }

            if (is_array($value) && $value !== []) {
                return true;
            }
        }

        return false;
    }

    private static function columnId(int $index): string
    {
        return 'col_'.$index.'_'.substr(md5((string) $index), 0, 4);
    }
}
