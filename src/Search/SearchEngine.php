<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\SearchDocumentIndex;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Google-like search over the denormalized index.
 *
 * Ranking is column-weighted (title × 3, excerpt × 2, body × 1) multiplied by
 * the entity weight, and matching is:
 *   - accent- and case-insensitive (the index uses a *_ci collation),
 *   - prefix-based (`larav*` finds "Laravel" and "Laravelista"),
 *   - AND across the typed words, so extra words narrow the result set.
 *
 * FULLTEXT has a minimum word length (innodb_ft_min_token_size, 3 by default),
 * so a literal LIKE pass backs it up whenever the full-text pass finds nothing;
 * that keeps short queries ("IA", "UX") working without giving up relevance for
 * the normal case.
 */
final class SearchEngine
{
    public function __construct(
        private readonly SearchSourceRegistryInterface $sources,
        private readonly EntitySchemaRegistryInterface $schemas,
        private readonly PublicContentDiscoveryInterface $discovery,
    ) {}

    public function search(
        string $query,
        ?string $locale = null,
        ?string $type = null,
        int $limit = 20,
        ?string $market = null,
        int $offset = 0,
    ): SearchResults {
        $query = $this->normalize($query);

        if ($query === '') {
            return SearchResults::empty($query);
        }

        $locale = $locale ?: (string) app()->getLocale();
        $words = $this->words($query);

        // Full-text pass: accent-insensitive, prefix based, AND across words.
        // InnoDB only publishes its FULLTEXT index on commit, so a freshly
        // written row may not be matched here yet — the literal pass below is
        // the honest safety net (and the normal path for words below the
        // full-text minimum token length).
        if ($words !== []) {
            $expression = $this->booleanExpression($words);
            $builder = $this->baseQuery($expression, $locale, $type, $market);
            $total = (clone $builder)->count();

            if ($total > 0) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, SearchDocumentIndex> $rows */
                $rows = (clone $builder)
                    ->selectRaw($this->relevanceExpression(), [$expression, $expression, $expression])
                    ->orderByDesc('relevance')
                    ->orderByDesc('weight')
                    ->orderBy('id')
                    ->offset(max(0, $offset))
                    ->limit(max(1, $limit))
                    ->get();

                return $this->results($query, $locale, $builder, $rows, $total, 'fulltext', $market);
            }
        }

        $fallback = $this->fallbackQuery($words === [] ? [$query] : $words, $locale, $type, $market);
        $total = (clone $fallback)->count();

        /** @var \Illuminate\Database\Eloquent\Collection<int, SearchDocumentIndex> $rows */
        $rows = (clone $fallback)
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->get();

        return $this->results($query, $locale, $fallback, $rows, $total, 'fallback', $market);
    }

    /**
     * @param  Builder<SearchDocumentIndex>  $builder
     * @param  \Illuminate\Database\Eloquent\Collection<int, SearchDocumentIndex>  $rows
     */
    private function results(
        string $query,
        string $locale,
        Builder $builder,
        \Illuminate\Database\Eloquent\Collection $rows,
        int $total,
        string $engine,
        ?string $market,
    ): SearchResults {
        $hits = [];
        $resolvedMarket = $market !== null && $rows->isNotEmpty() ? Market::where('code', $market)->first() : null;

        foreach ($rows as $row) {
            $hits[] = $this->toHit($row, $locale, $resolvedMarket);
        }

        return new SearchResults(
            query: $query,
            hits: $hits,
            total: $total,
            facets: $this->facets($builder, $locale),
            suggestions: $hits === [] ? $this->suggestions($query, $locale, $market) : [],
            engine: $engine,
        );
    }

    /**
     * Per-entity facet counts for the current query (used to refine results).
     *
     * @param  Builder<SearchDocumentIndex>  $builder
     * @return array<string, array{label: string, count: int}>
     */
    private function facets(Builder $builder, string $locale): array
    {
        /** @var Collection<int, object{type: string, total: int}> $rows */
        $rows = (clone $builder)
            ->reorder()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->get();

        $facets = [];

        foreach ($rows as $row) {
            $facets[$row->type] = [
                'label' => $this->label($row->type, $locale),
                'count' => (int) $row->total,
            ];
        }

        return $facets;
    }

    /**
     * "Did you mean…" candidates: existing titles that start with the first
     * typed word. Cheap, honest and always derived from real indexed content.
     *
     * @return list<string>
     */
    private function suggestions(string $query, string $locale, ?string $market): array
    {
        $suggestions = [];

        foreach ($this->words($query) as $word) {
            if (mb_strlen($word) < 2) {
                continue;
            }

            /** @var Collection<int, SearchDocumentIndex> $rows */
            $suggestionQuery = SearchDocumentIndex::query();
            $this->scopePublicContext($suggestionQuery, $locale, $market);
            $rows = $suggestionQuery
                ->whereIn('type', $this->sources->types())
                ->where('locale', $locale)
                ->where('title', 'like', $this->escapeLike($word).'%')
                ->orderByDesc('weight')
                ->limit(5)
                ->get();

            foreach ($rows as $row) {
                $title = trim($row->title);

                if ($title !== '' && ! in_array($title, $suggestions, true)) {
                    $suggestions[] = $title;
                }
            }

            if (count($suggestions) >= 5) {
                break;
            }
        }

        return array_slice($suggestions, 0, 5);
    }

    private function toHit(SearchDocumentIndex $row, string $locale, ?Market $market): SearchHit
    {
        $url = $row->url;
        if ($market !== null && $this->schemas->get($row->type) !== null) {
            $entity = $this->discovery->query($row->type, $market, $locale)->whereKey($row->entity_id)->first();
            $translation = $entity?->getRelationValue('translations')->firstWhere('locale', $locale);
            if ($translation !== null) {
                $url = $this->discovery->url($row->type, $market, $locale, (string) $translation->getAttribute('slug'));
            }
        }

        return new SearchHit(
            type: $row->type,
            label: $this->label($row->type, $locale),
            title: $row->title,
            excerpt: $row->excerpt,
            url: $url,
            relevance: (float) ($row->relevance ?? 0.0),
            imageUrl: $row->image_url,
        );
    }

    private function label(string $type, string $locale): string
    {
        $source = $this->sources->get($type);

        if ($source !== null) {
            return $source->label($locale);
        }

        return $this->schemas->get($type)->label ?? $type;
    }

    /**
     * @return Builder<SearchDocumentIndex>
     */
    private function baseQuery(string $expression, string $locale, ?string $type, ?string $market): Builder
    {
        $builder = SearchDocumentIndex::query()->where('locale', $locale)->whereIn('type', $this->sources->types());
        $this->scopePublicContext($builder, $locale, $market);

        if ($type !== null && $type !== '') {
            $builder->where('type', $type);
        }

        if ($market !== null && $market !== '') {
            $builder->where(function (Builder $inner) use ($market): void {
                $inner->where('market_code', $market)->orWhere('market_code', '');
            });
        }

        $builder->where(function (Builder $inner) use ($expression): void {
            $inner->whereRaw('MATCH(title) AGAINST (? IN BOOLEAN MODE)', [$expression])
                ->orWhereRaw('MATCH(excerpt) AGAINST (? IN BOOLEAN MODE)', [$expression])
                ->orWhereRaw('MATCH(body) AGAINST (? IN BOOLEAN MODE)', [$expression]);
        });

        return $builder;
    }

    /**
     * The index is denormalized and can outlive changes to public availability.
     * Live visibility must constrain the SQL query before count/facets/offset.
     *
     * @param  Builder<SearchDocumentIndex>  $builder
     */
    private function scopePublicContext(Builder $builder, string $locale, ?string $marketCode): void
    {
        if ($marketCode === null || $marketCode === '') {
            return;
        }
        $market = Market::where('code', $marketCode)->where('is_active', true)->first();
        if ($market === null) {
            $builder->whereRaw('1 = 0');

            return;
        }
        $builder->where(function (Builder $visible) use ($market, $locale): void {
            $visible->whereRaw('1 = 0');
            foreach ($this->sources->types() as $type) {
                if ($this->schemas->get($type) === null) {
                    continue;
                }
                $entities = $this->discovery->query($type, $market, $locale);
                $entities->select($entities->getModel()->qualifyColumn($entities->getModel()->getKeyName()));
                $visible->orWhere(fn (Builder $rows) => $rows->where('type', $type)->whereIn('entity_id', $entities));
            }
        });
    }

    /**
     * Literal pass: every typed word must appear in title, excerpt or body.
     *
     * Used when the full-text pass finds nothing — short words (below
     * innodb_ft_min_token_size), inflections and inside-word matches — and it
     * ranks like the full-text pass does: title match first, then entity weight.
     *
     * @param  list<string>  $words
     * @return Builder<SearchDocumentIndex>
     */
    private function fallbackQuery(array $words, string $locale, ?string $type, ?string $market): Builder
    {
        $builder = SearchDocumentIndex::query()->where('locale', $locale)->whereIn('type', $this->sources->types());
        $this->scopePublicContext($builder, $locale, $market);

        if ($type !== null && $type !== '') {
            $builder->where('type', $type);
        }

        if ($market !== null && $market !== '') {
            $builder->where(function (Builder $inner) use ($market): void {
                $inner->where('market_code', $market)->orWhere('market_code', '');
            });
        }

        foreach ($words as $word) {
            $term = '%'.$this->escapeLike($word).'%';

            $builder->where(function (Builder $inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('excerpt', 'like', $term)
                    ->orWhere('body', 'like', $term);
            });
        }

        $first = '%'.$this->escapeLike($words[0]).'%';

        return $builder
            ->orderByRaw(
                '(CASE WHEN title LIKE ? THEN 3 WHEN excerpt LIKE ? THEN 2 WHEN body LIKE ? THEN 1 ELSE 0 END) DESC',
                [$first, $first, $first],
            )
            ->orderByDesc('weight')
            ->orderBy('id');
    }

    /**
     * Column-weighted relevance: title × 3, excerpt × 2, body × 1, multiplied by
     * the entity weight. Written as one literal so the query builder's
     * literal-string (injection) guard keeps holding; the bindings are passed by
     * the caller.
     *
     * @return literal-string
     */
    private function relevanceExpression(): string
    {
        return 'search_documents.*, ('
            .'MATCH(title) AGAINST (? IN BOOLEAN MODE) * 3.0 + '
            .'MATCH(excerpt) AGAINST (? IN BOOLEAN MODE) * 2.0 + '
            .'MATCH(body) AGAINST (? IN BOOLEAN MODE) * 1.0'
            .') * weight as relevance';
    }

    /**
     * Turns the typed words into a boolean prefix expression: every word is
     * required (`+`) and prefix-matched (`*`).
     *
     * @param  list<string>  $words
     */
    private function booleanExpression(array $words): string
    {
        if ($words === []) {
            return '';
        }

        return implode(' ', array_map(static fn (string $word): string => '+'.$word.'*', $words));
    }

    /**
     * @return list<string>
     */
    private function words(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $query) ?: [];

        $words = array_values(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= 2,
        ));

        return array_slice($words, 0, 8);
    }

    private function normalize(string $query): string
    {
        $query = preg_replace('/\s+/u', ' ', trim($query)) ?? '';

        return mb_substr($query, 0, 120);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
