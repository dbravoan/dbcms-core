<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

use DbCms\Core\Models\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class MenuMutationHandler extends AbstractResourceMutationHandler
{
    public function rules(Model $model, bool $creating): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:80', Rule::unique('menus', 'slug')->ignore($model->getKey())], 'location' => ['required', 'string', 'max:60'], 'is_active' => ['boolean']];
    }

    public function prepare(Model $model, array $attributes, bool $creating): array
    {
        return $creating ? $attributes + ['is_active' => true] : $attributes;
    }

    public function deleting(Model $model): void
    {
        if (! $model instanceof Menu) {
            throw new \LogicException('Expected a menu.');
        }
        foreach ($model->items()->lockForUpdate()->get() as $item) {
            $item->translations()->delete();
            $item->delete();
        }
    }
}
