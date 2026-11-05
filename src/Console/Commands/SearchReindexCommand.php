<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Models\SearchDocumentIndex;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use DbCms\Core\Search\SearchIndexer;
use Illuminate\Console\Command;

/**
 * Rebuilds the cross-entity search index.
 *
 * Safe to run at any time: indexing is idempotent (upsert by entity + locale),
 * so `--fresh` is only about dropping rows of entities that no longer exist.
 */
final class SearchReindexCommand extends Command
{
    protected $signature = 'search:reindex
                            {--type= : Reindex a single entity type (post, product…)}
                            {--locale= : Reindex a single locale only}
                            {--keep : Do not flush the existing index first}
                            {--stats : Only report index coverage}';

    protected $description = 'Rebuild the search index of every registered entity';

    public function handle(SearchIndexer $indexer): int
    {
        $locale = $this->option('locale');
        $locale = is_string($locale) && $locale !== '' ? $locale : null;

        if ($this->option('stats')) {
            return $this->reportStats($indexer, $locale);
        }

        $type = $this->option('type');
        $type = is_string($type) && $type !== '' ? $type : null;

        if ($type !== null) {
            if (! app()->bound(SearchSourceRegistryInterface::class)) {
                $this->error('The search source registry is not available.');

                return self::FAILURE;
            }

            $indexed = $indexer->indexSource($type, $locale);
            $this->info("Indexed {$indexed} documents of type [{$type}].");

            return self::SUCCESS;
        }

        $counts = $indexer->indexAll($locale, fresh: ! $this->option('keep'));
        $total = array_sum($counts);

        foreach ($counts as $entityType => $count) {
            $this->line("  {$entityType}: {$count}");
        }

        $this->info("Search index rebuilt: {$total} documents".($locale !== null ? " for [{$locale}]" : '').'.');

        return self::SUCCESS;
    }

    private function reportStats(SearchIndexer $indexer, ?string $locale): int
    {
        $stats = $indexer->stats($locale);
        $rows = SearchDocumentIndex::query()
            ->selectRaw('type, locale, count(*) as total')
            ->groupBy('type', 'locale')
            ->get();

        foreach ($stats as $type => $data) {
            $this->line(sprintf('  %-16s %6d indexed  (%s)', $type, $data['indexed'], $data['label']));
        }

        $this->newLine();
        $this->line('By locale:');

        foreach ($rows as $row) {
            $data = (array) $row;

            $this->line(sprintf(
                '  %-16s %-6s %6d',
                (string) ($data['type'] ?? ''),
                (string) ($data['locale'] ?? ''),
                (int) ($data['total'] ?? 0),
            ));
        }

        return self::SUCCESS;
    }
}
