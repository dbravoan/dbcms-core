<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Contracts;

/** Owner-scoped dependency discovery over the same prepared rows as the search projection. */
interface BlockReferenceSearchSourceInterface
{
    /** @param list<int|string> $values
     * @return iterable<int,int|string> */
    public function referencingEntities(string $blockType, string $field, array $values): iterable;
}
