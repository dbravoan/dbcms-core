<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

interface SearchAnalyticsInterface
{
    /** @return list<array{term:string,hits:int,results:int}> */
    public function topTerms(?string $locale, int $limit = 10): array;
}
