<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Search\Contracts\SearchSourceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Module-owned projections with canonical documents for each public context. */
abstract class ContextualSearchSource implements SearchSourceInterface
{
    public function __construct(protected readonly PublicContentDiscoveryInterface $discovery) {}

    /** @return Builder<covariant Model> */
    abstract protected function query(): Builder;

    abstract protected function document(Model $entity, Model $translation, Market $market): ?SearchDocument;

    public function weight(): float
    {
        return 1.0;
    }

    public function documents(?string $locale = null): iterable
    {
        yield from $this->collect(null, $locale);
    }

    public function documentsFor(int|string $entityId): iterable
    {
        yield from $this->collect($entityId, null);
    }

    /** @return iterable<int,SearchDocument> */
    private function collect(int|string|null $entityId, ?string $locale): iterable
    {
        $markets = $this->discovery->markets();
        $query = $this->query();
        if ($entityId !== null) {
            $query->whereKey($entityId);
        }
        foreach ($query->lazyById(200) as $entity) {
            $memberships = $entity->getRelation('markets');
            foreach ($entity->getRelation('translations') as $translation) {
                $translationLocale = (string) $translation->getAttribute('locale');
                if ($locale !== null && $locale !== $translationLocale) {
                    continue;
                }
                foreach ($markets as $market) {
                    if (($memberships->isNotEmpty() && ! $memberships->contains('id', $market->getKey())) || ! $market->languages->contains('code', $translationLocale)) {
                        continue;
                    }
                    $document = $this->document($entity, $translation, $market);
                    if ($document !== null) {
                        yield $document;
                    }
                }
            }
        }
    }
}
