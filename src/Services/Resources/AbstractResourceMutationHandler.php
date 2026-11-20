<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

use DbCms\Core\Capabilities\Contracts\ResourceMutationHandlerInterface;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractResourceMutationHandler implements ResourceMutationHandlerInterface
{
    public function rules(Model $model, bool $creating): array
    {
        return [];
    }

    public function prepare(Model $model, array $attributes, bool $creating): array
    {
        return $attributes;
    }

    public function saved(Model $model, array $attributes, bool $creating): void {}

    public function deleting(Model $model): void {}

    protected function fail(string $field, string $message): never
    {
        throw new ResourceMutationException($field, $message);
    }
}
