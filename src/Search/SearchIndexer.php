<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use DbCms\Core\Blocks\Components\Services\ComponentSearchInvalidator;
use DbCms\Core\Models\SearchDocumentIndex;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Writes the denormalized search index.
 *
 * Indexing is idempotent (upsert by type + entity + locale + market) so a full
 * reindex can run at any time, and incremental reindexing only needs the entity
 * id (the module's source rebuilds that entity's documents).
 */
final class SearchIndexer
{
    private int $batchDepth = 0;

    /** @var array<string,array{type:string,id:int|string}> */
    private array $pendingEntities = [];

    public function __construct(
        private readonly SearchSourceRegistryInterface $sources,
    ) {}

    public function index(SearchDocument $document): void
    {
        SearchDocumentIndex::updateOrCreate(
            [
                'type' => $document->type,
                'entity_id' => $document->entityId,
                'locale' => $document->locale,
                'market_code' => $document->market ?? '',
            ],
            $document->toIndexAttributes(),
        );
    }

    /**
     * Reindexes the documents of a single entity (used when content changes).
     */
    public function indexEntity(string $type, int|string $entityId): int
    {
        if ($this->batchDepth > 0) {
            $this->pendingEntities[$type.':'.$entityId] = ['type' => $type, 'id' => $entityId];

            return 0;
        }
        if ($type === 'block_component') {
            app(ComponentSearchInvalidator::class)->refresh((int) $entityId);

            return 0;
        }
        $source = $this->sources->get($type);

        if ($source === null) {
            return 0;
        }

        $rows = [];
        foreach ($source->documentsFor($entityId) as $document) {
            $rows[] = $document->toIndexAttributes();
        }
        DB::transaction(function () use ($type, $entityId, $rows): void {
            $this->forgetEntity($type, $entityId);
            if ($rows !== []) {
                SearchDocumentIndex::upsert($rows, ['type', 'entity_id', 'locale', 'market_code']);
            }
        });

        return count($rows);
    }

    /**
     * @template T
     *
     * @param  callable():T  $operation
     * @return T
     */
    public function batch(callable $operation): mixed
    {
        $this->batchDepth++;
        $completed = false;
        try {
            $result = $operation();
            $completed = true;

            return $result;
        } finally {
            $this->batchDepth--;
            if ($this->batchDepth === 0) {
                $pending = $this->pendingEntities;
                $this->pendingEntities = [];
                if ($completed) {
                    foreach ($pending as $entity) {
                        $this->indexEntity($entity['type'], $entity['id']);
                    }
                }
            }
        }
    }

    /**
     * Reindexes one entity type, optionally for a single locale.
     */
    public function indexSource(string $type, ?string $locale = null, bool $replace = false): int
    {
        $source = $this->sources->get($type);

        if ($source === null) {
            return 0;
        }

        return DB::transaction(function () use ($source, $type, $locale, $replace): int {
            $count = 0;
            if ($replace) {
                SearchDocumentIndex::query()->where('type', $type)->when($locale !== null, fn ($query) => $query->where('locale', $locale))->delete();
            }
            $rows = [];
            foreach ($source->documents($locale) as $document) {
                $rows[] = $document->toIndexAttributes();
                $count++;
                if (count($rows) === 200) {
                    SearchDocumentIndex::upsert($rows, ['type', 'entity_id', 'locale', 'market_code']);
                    $rows = [];
                }
            }
            if ($rows !== []) {
                SearchDocumentIndex::upsert($rows, ['type', 'entity_id', 'locale', 'market_code']);
            }

            return $count;
        });
    }

    /**
     * @return array<string, int> documents indexed per entity type
     */
    public function indexAll(?string $locale = null, bool $fresh = true): array
    {
        return DB::transaction(function () use ($locale, $fresh): array {
            if ($fresh) {
                $this->flush();
            }
            $counts = [];
            foreach ($this->sources->types() as $type) {
                $counts[$type] = $this->indexSource($type, $locale);
            }

            return $counts;
        });
    }

    public function forgetEntity(string $type, int|string $entityId): int
    {
        return SearchDocumentIndex::query()
            ->where('type', $type)
            ->where('entity_id', (string) $entityId)
            ->delete();
    }

    public function flush(?string $type = null): int
    {
        $query = SearchDocumentIndex::query();

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->delete();
    }

    /**
     * Index coverage per entity type: registered sources and indexed documents.
     *
     * @return array<string, array{label: string, indexed: int}>
     */
    public function stats(?string $locale = null): array
    {
        $stats = [];

        foreach ($this->sources->all() as $type => $source) {
            $query = SearchDocumentIndex::query()->where('type', $type);

            if ($locale !== null) {
                $query->where('locale', $locale);
            }

            $stats[$type] = [
                'label' => $source->label($locale ?? 'es'),
                'indexed' => $query->count(),
            ];
        }

        return $stats;
    }
}
