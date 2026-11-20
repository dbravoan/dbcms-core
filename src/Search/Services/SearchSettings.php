<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Services;

use DbCms\Core\Services\Cms\CoreFeatureSettings;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Validation\ValidationException;

/**
 * Settings of the core public search, with the documented defaults.
 */
final class SearchSettings
{
    public const FEATURE = 'search';

    public function __construct(
        private readonly CoreFeatureSettings $settings,
        private readonly LegalPageService $legal,
    ) {}

    public function isIndexable(): bool
    {
        return (bool) $this->settings->get(self::FEATURE, 'is_indexable', false);
    }

    public function resultsPerPage(): int
    {
        return max(1, (int) $this->settings->get(self::FEATURE, 'results_per_page', 12));
    }

    public function minResultsToIndex(): int
    {
        return max(1, (int) $this->settings->get(self::FEATURE, 'min_results_to_index', 3));
    }

    public function tracksTerms(): bool
    {
        return (bool) $this->settings->get(self::FEATURE, 'track_terms', false);
    }

    public function retentionDays(): int
    {
        try {
            return (int) $this->legal->requireProcessing('search_terms')['retention_days'];
        } catch (ValidationException) {
            // The last reviewed window still applies to retained terms after withdrawal.
            return max(1, (int) $this->settings->get(self::FEATURE, 'retention_days', 90));
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->settings->set(self::FEATURE, $key, $value);
    }
}
