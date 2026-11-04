<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Front;

use DbCms\Core\Models\Market;

final readonly class PublicContentContext
{
    /** @param list<string> $segments
     * @param  array<string,mixed>  $query
     */
    public function __construct(public Market $market, public string $locale, public string $slug = '', public string $currentUrl = '', public array $segments = [], public array $query = [], public int $page = 1) {}
}
