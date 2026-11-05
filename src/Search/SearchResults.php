<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

/**
 * Result of a query: ranked hits, per-entity facets and (when nothing matched)
 * spelling/coverage suggestions so the empty state is never a dead end.
 */
final readonly class SearchResults
{
    /**
     * @param  list<SearchHit>  $hits
     * @param  array<string, array{label: string, count: int}>  $facets  entity type => label + count
     * @param  list<string>  $suggestions
     */
    public function __construct(
        public string $query,
        public array $hits = [],
        public int $total = 0,
        public array $facets = [],
        public array $suggestions = [],
        public string $engine = 'fulltext',
    ) {}

    public static function empty(string $query): self
    {
        return new self(query: $query);
    }

    public function hasResults(): bool
    {
        return $this->hits !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'total' => $this->total,
            'engine' => $this->engine,
            'facets' => $this->facets,
            'suggestions' => $this->suggestions,
            'hits' => array_map(static fn (SearchHit $hit): array => $hit->toArray(), $this->hits),
        ];
    }
}
