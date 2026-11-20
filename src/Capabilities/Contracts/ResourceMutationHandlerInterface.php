<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ResourceMutationHandlerInterface
{
    /** @return array<string,mixed> */
    public function rules(Model $model, bool $creating): array;

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    public function prepare(Model $model, array $attributes, bool $creating): array;

    /** @param array<string,mixed> $attributes */
    public function saved(Model $model, array $attributes, bool $creating): void;

    public function deleting(Model $model): void;
}
