<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Services;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\SearchDocumentIndex;
use DbCms\Core\Search\Contracts\SearchFrontPageServiceInterface;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use DbCms\Core\Search\DTOs\SearchFrontInput;
use DbCms\Core\Search\SearchEngine;
use DbCms\Core\Search\SearchResults;
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Core\Services\Seo\SeoMetaService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The general search front office.
 *
 * Grammar (no query strings in the address bar):
 *   /{market}/{locale}/{prefix}                 → hub (indexable once activated)
 *   /{market}/{locale}/{prefix}/{term}          → results
 *   /{market}/{locale}/{prefix}/{term}/{type}   → results of one entity type
 *
 * A plain GET form (`?q=…`, or a link to `?type=…`) is answered with a 301 to
 * the canonical friendly URL, exactly like every other filter surface of the
 * CMS, so the same result set never lives on two addresses.
 */
final class SearchFrontPageService implements SearchFrontPageServiceInterface
{
    public function __construct(
        private readonly LocalizationServiceInterface $localization,
        private readonly FrontViewResolver $views,
        private readonly SearchEngine $engine,
        private readonly SearchSourceRegistryInterface $sources,
        private readonly SearchSettings $settings,
        private readonly SearchAnalyticsService $analytics,
        private readonly SeoMetaService $seo,
        private readonly PublicContentDiscoveryInterface $discovery,
    ) {}

    public function hub(SearchFrontInput $input): FrontPageData
    {
        $market = $input->market;
        $locale = $input->locale;

        $typed = trim($input->query);

        if ($typed !== '') {
            return FrontPageData::redirect($this->url($market, $locale, Str::slug($typed)), 301);
        }

        $indexable = $this->settings->isIndexable();

        return FrontPageData::view($this->views->resolve('search.index', 'search::index'), [
            'layout' => $this->views->layout(),
            'currentMarket' => $market,
            'searchUrl' => $this->url($market, $locale),
            'coverage' => $this->publicCoverage($market, $locale),
            'popularTerms' => $this->analytics->topTerms($locale, 8),
            'indexable' => $indexable,
            'breadcrumbs' => $this->breadcrumbs($market, $locale, null),
            'seoMeta' => $this->seo->forWebPage(
                title: $this->copy('hub_title', $locale, 'Buscador'),
                description: $this->copy('hub_description', $locale, ''),
                canonical: $this->url($market, $locale),
                locale: $locale,
                robots: $indexable ? 'index, follow' : 'noindex, follow',
            ),
        ]);
    }

    public function results(SearchFrontInput $input): FrontPageData
    {
        $market = $input->market;
        $locale = $input->locale;

        $term = $input->term;
        $entityType = $input->entityType;

        if ($entityType !== null && ! $this->sources->has($entityType)) {
            return FrontPageData::missing();
        }

        $canonicalTerm = (string) Str::slug($term);

        if ($canonicalTerm === '') {
            return FrontPageData::redirect($this->url($market, $locale), 301);
        }

        if ($canonicalTerm !== $term) {
            return FrontPageData::redirect($this->url($market, $locale, $canonicalTerm.$this->typeSuffix($entityType)), 301);
        }

        $page = max(1, $input->page);
        $perPage = $this->settings->resultsPerPage();

        $results = $this->engine->search(
            query: $canonicalTerm,
            locale: $locale,
            type: $entityType,
            limit: $perPage,
            market: (string) $market->code,
            offset: ($page - 1) * $perPage,
        );

        $this->analytics->record($canonicalTerm, $locale, (string) $market->code, $results->total);

        $paginator = new LengthAwarePaginator(
            $results->hits,
            $results->total,
            $perPage,
            $page,
            ['path' => $this->url($market, $locale, $canonicalTerm.$this->typeSuffix($entityType))],
        );

        $indexable = $this->settings->isIndexable()
            && $page === 1
            && $results->total >= $this->settings->minResultsToIndex();

        $title = $this->copy('results_title', $locale, 'Resultados para %s');
        $title = str_replace('%s', $canonicalTerm, $title);
        $typeLabel = $entityType !== null ? $this->sources->get($entityType)?->label($locale) : null;

        if ($typeLabel !== null) {
            $title .= ' · '.$typeLabel;
        }

        return FrontPageData::view($this->views->resolve('search.results', 'search::results'), [
            'layout' => $this->views->layout(),
            'currentMarket' => $market,
            'term' => $canonicalTerm,
            'entityType' => $entityType,
            'typeLabel' => $typeLabel,
            'results' => $results,
            'hits' => $results->hits,
            'facets' => $this->facetLinks($market, $locale, $canonicalTerm, $results),
            'paginator' => $paginator,
            'suggestions' => $results->suggestions,
            'searchUrl' => $this->url($market, $locale),
            'indexable' => $indexable,
            'breadcrumbs' => $this->breadcrumbs($market, $locale, $canonicalTerm),
            'seoMeta' => $this->seo->forWebPage(
                title: $title,
                description: str_replace('%s', $canonicalTerm, $this->copy('results_description', $locale, '')),
                canonical: $this->url($market, $locale, $canonicalTerm.$this->typeSuffix($entityType)),
                locale: $locale,
                robots: $indexable ? 'index, follow' : 'noindex, follow',
                schemaOverride: $this->schema($results, $this->url($market, $locale, $canonicalTerm.$this->typeSuffix($entityType))),
            ),
        ]);
    }

    private function url(Market $market, string $locale, string $path = ''): string
    {
        return $this->localization->frontUrl('search', $path, $locale, $market);
    }

    /** @return array<string,array{label:string,indexed:int}> */
    private function publicCoverage(Market $market, string $locale): array
    {
        $coverage = [];
        foreach ($this->sources->all() as $type => $source) {
            $visible = $this->discovery->query($type, $market, $locale);
            $count = SearchDocumentIndex::query()->where('type', $type)->where('locale', $locale)
                ->where(fn ($context) => $context->where('market_code', $market->code)->orWhere('market_code', ''))
                ->whereIn('entity_id', $visible->select($visible->getModel()->getQualifiedKeyName()))
                ->distinct()->count('entity_id');
            $coverage[$type] = ['label' => $source->label($locale), 'indexed' => $count];
        }

        return $coverage;
    }

    private function typeSuffix(?string $entityType): string
    {
        return $entityType !== null && $entityType !== '' ? '/'.$entityType : '';
    }

    /**
     * @return array<int, array{title: string, url: string}>
     */
    private function breadcrumbs(Market $market, string $locale, ?string $term): array
    {
        $trail = [
            ['title' => $this->copy('home', $locale, 'Inicio'), 'url' => $this->localization->url('/', $market, $locale)],
            ['title' => $this->copy('hub_title', $locale, 'Buscador'), 'url' => $this->url($market, $locale)],
        ];

        if ($term !== null && $term !== '') {
            $trail[] = ['title' => $term, 'url' => $this->url($market, $locale, $term)];
        }

        return $trail;
    }

    /**
     * Per-entity facet links (canonical, crawlable, no query string).
     *
     * @return array<int, array{key: string, label: string, count: int, url: string, active: bool}>
     */
    private function facetLinks(Market $market, string $locale, string $term, SearchResults $results): array
    {
        $links = [];

        foreach ($results->facets as $type => $facet) {
            $links[] = [
                'key' => $type,
                'label' => $facet['label'],
                'count' => $facet['count'],
                'url' => $this->url($market, $locale, $term.'/'.$type),
                'active' => false,
            ];
        }

        return $links;
    }

    /**
     * SearchResultsPage + ItemList graph: search engines and AI answer engines
     * understand the page is a result list of links.
     *
     * @return array<string, mixed>
     */
    private function schema(SearchResults $results, string $url): array
    {
        $items = [];

        foreach ($results->toArray()['hits'] as $index => $hit) {
            if (! is_array($hit)) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string) ($hit['title'] ?? ''),
                'url' => (string) ($hit['url'] ?? ''),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'SearchResultsPage',
            'url' => $url,
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $results->total,
                'itemListElement' => $items,
            ],
        ];
    }

    /**
     * Localized copy for the search surfaces (kept in code: the core search
     * ships with the core, so it must not depend on a translation file).
     */
    private function copy(string $key, string $locale, string $fallback): string
    {
        $strings = [
            'hub_title' => ['es' => 'Buscador', 'en' => 'Search', 'ca' => 'Cercador', 'fr' => 'Recherche', 'de' => 'Suche'],
            'hub_description' => [
                'es' => 'Busca en todo el contenido del sitio: artículos, productos y páginas.',
                'en' => 'Search all the content of the site: articles, products and pages.',
                'ca' => 'Cerca a tot el contingut del lloc: articles, productes i pàgines.',
            ],
            'results_title' => ['es' => 'Resultados para %s', 'en' => 'Results for %s', 'ca' => 'Resultats per a %s'],
            'results_description' => [
                'es' => 'Resultados de búsqueda relacionados con %s.',
                'en' => 'Search results related to %s.',
                'ca' => 'Resultats de cerca relacionats amb %s.',
            ],
            'home' => ['es' => 'Inicio', 'en' => 'Home', 'ca' => 'Inici'],
        ];

        $value = $strings[$key][$locale] ?? $strings[$key]['es'] ?? $fallback;

        return $value;
    }

    /**
     * Languages of the market (used by the views for localized labels).
     *
     * @return Collection<int, Language>
     */
    public function languages(Market $market): Collection
    {
        return $market->languages()->where('is_active', true)->get();
    }
}
