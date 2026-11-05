<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use DbCms\Core\Search\SearchEngine;
use DbCms\Core\Search\SearchHit;
use Illuminate\Support\Str;

/**
 * WebMCP surface of the general search.
 *
 * Agents get the same contract a human gets: the query is answered with the
 * indexed entities, each one with its canonical front-office URL, plus the
 * search hub URL, the per-entity facets and the index policy of the result page
 * (a page with too little content is `noindex`, so an agent never cites it as a
 * citable source).
 */
class SearchNavigationService
{
    public function __construct(
        private readonly SearchEngine $engine,
        private readonly SearchSourceRegistryInterface $sources,
        private readonly LocalizationServiceInterface $localization,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function search(array $args): array
    {
        $market = $this->market(isset($args['market']) ? (string) $args['market'] : null);

        if ($market === null) {
            return ['error' => 'No market available.'];
        }

        $locale = $this->locale($market, isset($args['locale']) ? (string) $args['locale'] : null);
        $query = trim((string) ($args['query'] ?? ''));

        if ($query === '') {
            return ['error' => 'A non-empty "query" is required.', 'hub_url' => $this->hubUrl($market, $locale)];
        }

        $type = isset($args['type']) && $args['type'] !== '' ? (string) $args['type'] : null;

        if ($type !== null && ! $this->sources->has($type)) {
            return [
                'error' => "Unknown entity type [{$type}].",
                'available_types' => $this->sources->types(),
            ];
        }

        $limit = max(1, min(50, (int) ($args['limit'] ?? 10)));
        $results = $this->engine->search($query, $locale, $type, $limit, (string) $market->code);

        $indexable = (bool) module_setting('search', 'is_indexable', false)
            && $results->total >= max(1, (int) module_setting('search', 'min_results_to_index', 3));

        return [
            'query' => $results->query,
            'market' => (string) $market->code,
            'locale' => $locale,
            'engine' => $results->engine,
            'total' => $results->total,
            'canonical_url' => $this->resultUrl($market, $locale, $results->query),
            'hub_url' => $this->hubUrl($market, $locale),
            'index_policy' => $indexable ? 'index' : 'noindex',
            'facets' => $results->facets,
            'suggestions' => $results->suggestions,
            'results' => array_map(
                static fn (SearchHit $hit): array => $hit->toArray(),
                $results->hits,
            ),
        ];
    }

    private function hubUrl(Market $market, string $locale): string
    {
        return $this->localization->frontUrl('search', '', $locale, $market);
    }

    private function resultUrl(Market $market, string $locale, string $query): string
    {
        return $this->localization->frontUrl('search', (string) Str::slug($query), $locale, $market);
    }

    private function market(?string $code): ?Market
    {
        if ($code !== null && $code !== '') {
            return Market::query()->where('code', $code)->where('is_active', true)->first();
        }

        return Market::query()->where('is_default', true)->first()
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

        return 'es';
    }
}
