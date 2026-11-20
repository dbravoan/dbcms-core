<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Capabilities;

use DbCms\Core\Contracts\Services\SearchAnalyticsInterface;
use DbCms\Core\Search\SearchIndexer;

final class SearchActions
{
    public function __construct(private readonly SearchIndexer $indexer, private readonly SearchAnalyticsInterface $searchAnalytics) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function searchReindex(array $args): array
    {
        $type = isset($args['type']) && is_string($args['type']) && $args['type'] !== '' ? $args['type'] : null;
        $locale = isset($args['locale']) && is_string($args['locale']) && $args['locale'] !== '' ? $args['locale'] : null;
        $keep = filter_var($args['keep'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($type !== null) {
            $indexed = $this->indexer->indexSource($type, $locale, replace: ! $keep);

            return ['type' => $type, 'locale' => $locale, 'indexed' => $indexed];
        }

        $counts = $this->indexer->indexAll($locale, fresh: ! $keep);

        return [
            'indexed' => $counts,
            'total' => array_sum($counts),
            'locale' => $locale,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function searchStats(array $args): array
    {
        $locale = isset($args['locale']) && is_string($args['locale']) ? $args['locale'] : null;

        return [
            'coverage' => $this->indexer->stats($locale),
            'top_terms' => $this->searchAnalytics->topTerms($locale, 10),
        ];
    }
}
