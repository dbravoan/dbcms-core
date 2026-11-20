<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Services;

use DbCms\Core\Search\Models\SearchTerm;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Records what the front office actually searches for.
 *
 * The inbox ("Filtros y búsquedas") uses it to decide what deserves an indexed
 * page and what content is missing. Only the normalized term and counters are
 * stored — never the visitor, the IP or the session.
 */
final class SearchAnalyticsService
{
    public function __construct(
        private readonly SearchSettings $settings,
    ) {}

    /**
     * Records one search. Throttled per term+locale+market so a refreshed page
     * does not inflate the counters.
     */
    public function record(string $term, string $locale, string $market, int $results): void
    {
        if (! $this->settings->tracksTerms() || ! app(LegalPageService::class)->canCollect('search_terms')) {
            return;
        }

        $term = $this->normalize($term);

        if ($term === '') {
            return;
        }

        $minutes = (int) config('cms.search_analytics_throttle', 10);

        if ($minutes > 0 && ! Cache::add($this->cacheKey($term, $locale, $market), true, now()->addMinutes($minutes))) {
            return;
        }

        try {
            $row = SearchTerm::query()->firstOrNew([
                'term' => $term,
                'locale' => $locale,
                'market_code' => $market,
            ]);

            $row->hits = ((int) $row->hits) + 1;
            $row->results = $results;
            $row->zero_results = $results === 0 ? ((int) $row->zero_results) + 1 : 0;
            $row->last_seen_at = now();
            $row->save();
        } catch (\Throwable) {
            // Analytics must never break the search page.
        }
    }

    /**
     * @return Collection<int, SearchTerm>
     */
    public function topTerms(string $locale, int $limit = 8): Collection
    {
        return SearchTerm::query()->locale($locale)->popular()->limit($limit)->get();
    }

    /**
     * Searches that found nothing: the honest to-do list for content or curation.
     *
     * @return Collection<int, SearchTerm>
     */
    public function unsatisfiedTerms(string $locale, int $limit = 10): Collection
    {
        return SearchTerm::query()->locale($locale)->unsatisfied()->limit($limit)->get();
    }

    public function normalize(string $term): string
    {
        $term = preg_replace('/\s+/u', ' ', trim($term)) ?? '';

        return mb_substr(mb_strtolower($term), 0, 191);
    }

    private function cacheKey(string $term, string $locale, string $market): string
    {
        return 'search-term:'.md5($term.'|'.$locale.'|'.$market);
    }
}
