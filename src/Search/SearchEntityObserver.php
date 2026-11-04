<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/** Rebuild from committed live data, including relation edits and removals. */
abstract class SearchEntityObserver implements ShouldHandleEventsAfterCommit
{
    abstract protected function type(): string;

    abstract protected function entityId(Model $model): int|string|null;

    public function saved(Model $model): void
    {
        $id = $this->entityId($model);
        if (is_int($id) || is_string($id)) {
            app(SearchIndexer::class)->indexEntity($this->type(), $id);
        }
    }

    public function deleted(Model $model): void
    {
        $this->saved($model);
    }
}
