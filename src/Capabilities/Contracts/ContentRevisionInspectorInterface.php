<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ContentRevisionInspectorInterface
{
    /** @return array<string,mixed>|null */
    public function fingerprint(Model $entity): ?array;

    /** @return array<string,mixed>|null */
    public function preview(Model $entity): ?array;
}
