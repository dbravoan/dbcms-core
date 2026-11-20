<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use Illuminate\Support\Facades\DB;

/** Administrative context changes refresh canonical market/locale projections. */
final class SearchContextRebuilder
{
    public function __construct(private readonly SearchIndexer $indexer) {}

    public function afterCommit(): void
    {
        DB::afterCommit(fn () => $this->indexer->indexAll());
    }
}
