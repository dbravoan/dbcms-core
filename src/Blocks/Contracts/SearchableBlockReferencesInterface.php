<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

use DbCms\Core\Models\Market;

/** Owner-scoped reference discovery avoids transferring an entire content catalogue. */
interface SearchableBlockReferencesInterface extends ContextualBlockReferencesInterface
{
    /** @return array{items:list<array<string,mixed>>,has_more:bool} */
    public function searchReferences(Market $market, string $locale, string $source, string $search, int $limit, int $offset): array;

    public function referenceExists(Market $market, string $locale, string $source, string $key, string|int $value): bool;
}
