<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use Illuminate\Database\Eloquent\Model;

/** Optional module-owned market relationship persistence, inside the CRUD transaction. */
interface EntityRelationMutationHandlerInterface
{
    /** @param array<string,mixed> $args */
    public function syncRelations(Model $entity, array $args): void;
}
