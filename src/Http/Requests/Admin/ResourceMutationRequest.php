<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Requests\Admin;

use DbCms\Core\Services\Capabilities\ResourceCrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

final class ResourceMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $service = app(ResourceCrudService::class);
        $name = (string) $this->route()?->getName();
        $key = match (true) {
            str_starts_with($name, 'admin.markets.') => 'market',
            str_starts_with($name, 'admin.languages.') => 'language',
            str_starts_with($name, 'admin.users.') => 'user',
            $name === 'admin.menus.store-item' => 'menu_item',
            default => 'menu',
        };
        $parameter = $key === 'menu_item' ? 'item' : $key;
        $model = $this->route($parameter);
        $model = $model instanceof Model ? $model : null;
        $rules = $service->rulesFor($service->resolve($key), model: $model);
        if ($name === 'admin.users.update-role') {
            return ['role_id' => $rules['role_id']];
        }
        if ($model !== null && in_array($key, ['market', 'language'], true)) {
            unset($rules['code']);
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route()?->getName() === 'admin.menus.store-item') {
            $menu = $this->route('menu');
            if ($menu instanceof Model) {
                $this->merge(['menu_id' => $menu->getKey()]);
            }
        }
        if ($this->route()?->getName() === 'admin.markets.update' && ! $this->has('is_active')) {
            $this->merge(['is_active' => false]);
        }
    }
}
