<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

use DbCms\Core\Models\Market;

/** Public projections and filters are explicitly owned by the supplying entity package. */
interface BlockDataSourceInterface
{
    /** @return array<string,array<string,mixed>> */
    public function fields(): array;

    /** @return array<string,array<string,mixed>> */
    public function filters(): array;

    /** @param array<string,mixed> $filters
     * @return array{items:list<array<string,mixed>>,has_more:bool} */
    public function items(Market $market, string $locale, array $filters, int $page, int $limit): array;
}
