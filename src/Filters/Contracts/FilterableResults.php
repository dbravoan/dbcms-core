<?php

declare(strict_types=1);

namespace DbCms\Core\Filters\Contracts;

use DbCms\Core\Filters\FilterSet;
use DbCms\Core\Models\Market;

/**
 * Optional capability of a filterable module: run a filter request and return a
 * navigable projection of the results.
 *
 * Implemented by the blog and the shop so any consumer — the MCP/WebMCP server,
 * a future API or a CLI command — can browse the catalogue through the exact
 * same filter semantics as the front-office.
 */
interface FilterableResults
{
    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     canonical_path: string
     * }
     */
    public function browse(FilterSet $set, Market $market, string $locale, int $perPage = 10): array;
}
