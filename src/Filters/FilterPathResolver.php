<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Filters\Contracts\FilterableModule;
use DbCms\Core\Filters\Contracts\FilterableResults;
use DbCms\Core\Models\Market;

/**
 * Resolves a friendly filter path into a {@see FilterSet} plus its canonical
 * form, for any registered module.
 *
 * Shared by the backoffice curation screen and the MCP tools, so both agree on
 * what a path means and on which URL is canonical.
 */
final class FilterPathResolver
{
    public function __construct(
        private readonly FilterModuleRegistry $modules,
        private readonly FilterPathParser $parser,
        private readonly FilterUrlBuilder $builder,
    ) {}

    /**
     * @return array{module: FilterableModule, set: FilterSet, canonical: string}|null
     */
    public function resolve(string $moduleKey, string $marketCode, string $locale, string $path): ?array
    {
        $module = $this->modules->get($moduleKey);

        if ($module === null) {
            return null;
        }

        $dimensions = $module->filterDimensions();
        $set = $this->parser->parse($moduleKey, $marketCode, $locale, trim($path, '/'), $dimensions);

        if ($set === null || $set->isEmpty()) {
            return null;
        }

        $canonical = $this->builder->canonicalPath($set, $dimensions, $locale);

        if ($canonical === null || $canonical === '') {
            return null;
        }

        return ['module' => $module, 'set' => $set, 'canonical' => $canonical];
    }

    public function module(string $key): ?FilterableModule
    {
        return $this->modules->get($key);
    }

    /**
     * Whether a resolved filter set passes the published quality gate
     * (`min_results`): curated landings below the threshold are `noindex` at
     * runtime, so they must never enter the sitemap or llms.txt either.
     *
     * @param  array{module: FilterableModule, set: FilterSet, canonical: string}  $resolved
     */
    public function meetsQualityGate(array $resolved, Market $market, string $locale): bool
    {
        return $this->resultCount($resolved, $market, $locale) >= (int) config('front_filters.limits.min_results', 3);
    }

    /**
     * Number of results a resolved filter request produces (0 when the module
     * does not expose browsable results).
     *
     * @param  array{module: FilterableModule, set: FilterSet, canonical: string}  $resolved
     */
    public function resultCount(array $resolved, Market $market, string $locale): int
    {
        $module = $resolved['module'];

        if (! $module instanceof FilterableResults) {
            return 0;
        }

        return (int) $module->browse($resolved['set'], $market, $locale, 1)['total'];
    }

    /**
     * @return array<string, FilterableModule>
     */
    public function modules(): array
    {
        return $this->modules->all();
    }
}
