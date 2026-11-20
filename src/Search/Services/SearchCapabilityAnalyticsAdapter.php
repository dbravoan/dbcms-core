<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Services;

use DbCms\Core\Contracts\Services\SearchAnalyticsInterface;
use DbCms\Core\Search\Models\SearchTerm;

/** Authorized private metrics from the core search. */
final class SearchCapabilityAnalyticsAdapter implements SearchAnalyticsInterface
{
    public function topTerms(?string $locale, int $limit = 10): array
    {
        $terms = SearchTerm::query()->when($locale !== null, fn ($query) => $query->where('locale', $locale))
            ->popular()->limit(min(100, max(1, $limit)))->get()
            ->map(static fn (SearchTerm $term): array => ['term' => (string) $term->term, 'hits' => (int) $term->hits, 'results' => (int) $term->results])->values()->all();

        return array_values($terms);
    }
}
