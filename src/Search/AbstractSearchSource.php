<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use DbCms\Core\Search\Contracts\BlockReferenceSearchSourceInterface;
use DbCms\Core\Search\Contracts\SearchSourceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for module sources backed by a translated Eloquent model.
 *
 * Subclasses only declare their query (published/active rows), how to reach the
 * translations and how to project a translation into a {@see SearchDocument};
 * iteration, locale filtering and incremental reindexing are shared, so every
 * module's source stays a few lines long.
 */
abstract class AbstractSearchSource implements BlockReferenceSearchSourceInterface, SearchSourceInterface
{
    use ReadsBlockReferences;

    /**
     * Base query of the source: it must already constrain to indexable rows.
     *
     * @return Builder<covariant Model>
     */
    abstract protected function query(): Builder;

    /**
     * @return iterable<int, Model>
     */
    abstract protected function translations(Model $entity): iterable;

    abstract protected function document(Model $entity, Model $translation): ?SearchDocument;

    public function weight(): float
    {
        return 1.0;
    }

    /**
     * @return iterable<int, SearchDocument>
     */
    public function documents(?string $locale = null): iterable
    {
        foreach ($this->query()->get() as $entity) {
            yield from $this->each($entity, $locale);
        }
    }

    /**
     * @return iterable<int, SearchDocument>
     */
    public function documentsFor(int|string $entityId): iterable
    {
        $entity = $this->query()->whereKey($entityId)->first();

        if ($entity === null) {
            return;
        }

        yield from $this->each($entity, null);
    }

    /**
     * @return iterable<int, SearchDocument>
     */
    private function each(Model $entity, ?string $locale): iterable
    {
        foreach ($this->translations($entity) as $translation) {
            $translationLocale = (string) $translation->getAttribute('locale');

            if ($translationLocale === '' || ($locale !== null && $translationLocale !== $locale)) {
                continue;
            }

            $document = $this->document($entity, $translation);

            if ($document !== null) {
                yield $document;
            }
        }
    }
}
