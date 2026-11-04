<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use Illuminate\Database\Eloquent\Model;

/** Module-owned, safe relation values exposed by the shared entity reader. */
interface EntityRelationPayloadHandlerInterface
{
    /** @return array<string,mixed> */
    public function relationPayload(Model $entity): array;
}
