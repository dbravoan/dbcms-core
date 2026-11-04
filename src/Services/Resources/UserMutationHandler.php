<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

use DbCms\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class UserMutationHandler extends AbstractResourceMutationHandler
{
    public function rules(Model $model, bool $creating): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($model->getKey())], 'password' => ['required', 'string', 'min:8'], 'role_id' => ['required', 'integer', 'exists:roles,id'], 'locale' => ['nullable', 'string', 'exists:languages,code']];
    }

    public function prepare(Model $model, array $attributes, bool $creating): array
    {
        if (isset($attributes['email'])) {
            $attributes['email'] = strtolower(trim((string) $attributes['email']));
        }
        if (! $creating && array_key_exists('role_id', $attributes) && App::isProduction() && (bool) config('cms.lock_roles_in_production', true)) {
            $this->fail('error', 'La modificación de roles está bloqueada en entorno de producción por directivas de seguridad RBAC.');
        }

        return $attributes;
    }

    public function saved(Model $model, array $attributes, bool $creating): void
    {
        if (! $model instanceof User) {
            throw new \LogicException('Expected a user.');
        }
        if (array_key_exists('role_id', $attributes)) {
            $model->roles()->sync([(int) $attributes['role_id']]);
        }
    }

    public function deleting(Model $model): void
    {
        if (! $model instanceof User) {
            throw new \LogicException('Expected a user.');
        }
        if ((int) Auth::id() === (int) $model->getKey()) {
            $this->fail('error', 'No puedes eliminar tu propia cuenta en sesión activa.');
        }
    }
}
