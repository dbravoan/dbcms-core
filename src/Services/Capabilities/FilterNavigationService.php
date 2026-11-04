<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Filters\Contracts\FilterableModule;
use DbCms\Core\Filters\Contracts\FilterableResults;
use DbCms\Core\Filters\FilterAlternateUrls;
use DbCms\Core\Filters\FilterDimension;
use DbCms\Core\Filters\FilterIntroRenderer;
use DbCms\Core\Filters\FilterModuleRegistry;
use DbCms\Core\Filters\FilterPathResolver;
use DbCms\Core\Filters\FilterSeoResolver;
use DbCms\Core\Filters\FilterSet;
use DbCms\Core\Filters\FilterUrlBuilder;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;

/**
 * WebMCP navigation of the dynamic filter space.
 *
 * Exposes the same vocabulary, grammar and canonicalization the front-office
 * uses, so an AI agent can discover the facets, build **valid canonical URLs**
 * and browse the results instead of guessing query strings.
 */
class FilterNavigationService
{
    public function __construct(
        private readonly FilterPathResolver $paths,
        private readonly FilterModuleRegistry $modules,
        private readonly FilterUrlBuilder $builder,
        private readonly FilterSeoResolver $seo,
        private readonly FilterAlternateUrls $alternates,
        private readonly FilterIntroRenderer $intro,
    ) {}

    /**
     * Addressable dimensions (with their localized markers and vocabulary) for
     * one module or for every registered one.
     *
     * @return array<string, mixed>
     */
    public function dimensions(?string $moduleKey = null, ?string $marketCode = null, ?string $locale = null): array
    {
        $market = $this->market($marketCode);
        $locale = $this->locale($market, $locale);

        if ($market === null || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return ['error' => 'The market or language is unavailable.'];
        }

        $modules = $moduleKey !== null
            ? array_filter([$moduleKey => $this->modules->get($moduleKey)])
            : $this->modules->all();

        if ($modules === []) {
            return ['error' => "Unknown filterable module [{$moduleKey}]."];
        }

        $out = [
            'market' => $market->code,
            'locale' => $locale,
            'modules' => [],
        ];

        foreach ($modules as $key => $module) {
            $dimensions = [];

            foreach ($module->filterDimensions() as $dimension) {
                $dimensions[] = [
                    'key' => $dimension->key,
                    'type' => $dimension->type,
                    'marker' => $dimension->marker($locale),
                    'implicit' => $dimension->implicit,
                    'required' => $dimension->key === 'search' ? false : null,
                    'values' => $this->values($dimension, $locale, $market),
                ];
            }

            $prefix = app(LocalizationServiceInterface::class)->getModulePrefix($key, $locale);

            $out['modules'][$key] = [
                'label' => $module->filterModuleLabel($locale),
                'base_url' => url("/{$market->code}/{$locale}/{$prefix}"),
                'path_template' => '{market}/{locale}/'.$prefix.'/{dimension}/{value}/…',
                'dimensions' => $dimensions,
            ];
        }

        return $out;
    }

    /**
     * Resolve a friendly path (or full URL) into its canonical form, hreflang
     * cluster, SEO text and index policy.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $moduleKey, string $path, ?string $marketCode = null, ?string $locale = null): array
    {
        $market = $this->market($marketCode);
        $locale = $this->locale($market, $locale);

        if ($market === null || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return ['error' => 'The market or language is unavailable.'];
        }

        // Accept a full URL as well as a path.
        if (str_contains($path, '://')) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = $this->stripPrefix($path, (string) $market->code, $locale, $moduleKey);

        $resolved = $this->paths->resolve($moduleKey, (string) $market->code, $locale, $path);

        if ($resolved === null) {
            return ['error' => 'The path is not a valid, unambiguous filter request for that module.'];
        }

        /** @var FilterableModule $module */
        $module = $resolved['module'];
        $set = $resolved['set'];
        $dimensions = $module->filterDimensions();
        $prefix = app(LocalizationServiceInterface::class)->getModulePrefix($moduleKey, $locale);
        $total = $module instanceof FilterableResults
            ? $module->browse($set, $market, $locale, 1)['total']
            : 0;

        $filterSeo = $this->seo->resolve($module, $set, $dimensions, $total, (string) $market->code);

        $curated = $this->seo->curated($module, $set, (string) $market->code);

        return [
            'module' => $moduleKey,
            'market' => $market->code,
            'locale' => $locale,
            'filters' => $set->values,
            'signature' => $set->signature(),
            'canonical_path' => $resolved['canonical'],
            'canonical_url' => url("/{$market->code}/{$locale}/{$prefix}/{$resolved['canonical']}"),
            'results' => $total,
            'indexable' => $filterSeo->indexable,
            'robots' => $filterSeo->robots,
            'curated' => $filterSeo->curated,
            'title' => $filterSeo->title,
            'heading' => $filterSeo->heading,
            'description' => $filterSeo->description,
            // Curated intro (blocks composed in the backoffice), plain text for agents.
            'intro' => $this->intro->text($curated, $market, $locale),
            'alternates' => $this->alternates->build($set, $dimensions, $module, $market, app(LocalizationServiceInterface::class)),
        ];
    }

    /**
     * Browse a module through the filter framework.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function browse(string $moduleKey, array $args = []): array
    {
        $market = $this->market(isset($args['market']) ? (string) $args['market'] : null);
        $locale = $this->locale($market, isset($args['locale']) ? (string) $args['locale'] : null);

        if ($market === null || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return ['error' => 'The market or language is unavailable.'];
        }

        $module = $this->modules->get($moduleKey);

        if (! $module instanceof FilterableResults) {
            return ['error' => "Module [{$moduleKey}] does not expose browsable results."];
        }

        $path = isset($args['path']) ? (string) $args['path'] : '';

        if ($path === '' && isset($args['filters']) && is_array($args['filters'])) {
            // Accept a plain filters map and canonicalize it.
            $path = (string) ($this->builder->canonicalPath(
                new FilterSet($moduleKey, (string) $market->code, $locale, $args['filters']),
                $module->filterDimensions(),
                $locale
            ) ?? '');
        }

        $set = $path === ''
            ? new FilterSet($moduleKey, (string) $market->code, $locale, [])
            : ($this->paths->resolve($moduleKey, (string) $market->code, $locale, $path)['set'] ?? null);

        if ($set === null) {
            return ['error' => 'The filter path is not valid, unambiguous and within the SEO limits.'];
        }

        $perPage = max(1, min(50, (int) ($args['per_page'] ?? 10)));
        $result = $module->browse($set, $market, $locale, $perPage);
        $prefix = app(LocalizationServiceInterface::class)->getModulePrefix($moduleKey, $locale);

        return [
            'module' => $moduleKey,
            'market' => $market->code,
            'locale' => $locale,
            'filters' => $set->values,
            'total' => $result['total'],
            'canonical_url' => $result['canonical_path'] === ''
                ? url("/{$market->code}/{$locale}/{$prefix}")
                : url("/{$market->code}/{$locale}/{$prefix}/{$result['canonical_path']}"),
            'items' => $result['items'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function values(FilterDimension $dimension, string $locale, ?Market $market): array
    {
        if ($dimension->type === 'enum') {
            $values = [];

            foreach ($dimension->optionLabels($locale) as $value => $label) {
                $values[] = ['value' => (string) $value, 'label' => $label];
            }

            return $values;
        }

        if ($dimension->type !== 'terms' || $market === null) {
            return [];
        }

        $values = [];

        foreach (array_slice($dimension->labels($locale, (string) $market->code), 0, 30, true) as $slug => $label) {
            $values[] = ['value' => (string) $slug, 'label' => $label];
        }

        return $values;
    }

    private function stripPrefix(string $path, string $marketCode, string $locale, string $moduleKey): string
    {
        $prefix = app(LocalizationServiceInterface::class)->getModulePrefix($moduleKey, $locale);
        $path = '/'.trim($path, '/').'/';

        foreach (["/{$marketCode}/{$locale}/{$prefix}/", "/{$marketCode}/{$locale}/{$prefix}/"] as $needle) {
            if (str_contains($path, $needle)) {
                $path = substr($path, (int) strpos($path, $needle) + strlen($needle));

                break;
            }
        }

        return trim($path, '/');
    }

    private function market(?string $code): ?Market
    {
        if ($code !== null && $code !== '') {
            return Market::query()->where('code', $code)->where('is_active', true)->first();
        }

        return Market::query()->where('is_default', true)->where('is_active', true)->first()
            ?? Market::query()->where('is_active', true)->orderBy('id')->first();
    }

    private function locale(?Market $market, ?string $locale): string
    {
        if ($locale !== null && $locale !== '') {
            return $locale;
        }

        if ($market !== null) {
            $fallback = Language::query()
                ->where('is_active', true)
                ->where('code', $market->default_locale)
                ->first();

            if ($fallback !== null) {
                return (string) $fallback->code;
            }
        }

        return (string) (Language::query()->where('is_default', true)->first()->code ?? 'es');
    }
}
