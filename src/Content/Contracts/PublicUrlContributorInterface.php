<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use DbCms\Core\Models\Market;

/** Public destinations that do not correspond to stored content entities. */
interface PublicUrlContributorInterface
{
    public function ownerModule(): string;

    /** @return array<string, string> Absolute URL => public label. */
    public function urls(Market $market, string $locale): array;
}
