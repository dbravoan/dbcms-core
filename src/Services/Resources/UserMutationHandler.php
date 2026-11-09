<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Models\Role;
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

        $actor = $this->actor();
        if (array_key_exists('role_id', $attributes) && $actor instanceof User && ! $actor->isAdmin()) {
            if (! $creating && (int) $actor->getKey() === (int) $model->getKey()) {
                $this->fail('role_id', 'No puedes cambiar tu propio rol.');
            }

            $role = Role::with('permissions')->find((int) $attributes['role_id']);
            if (! $role instanceof Role) {
                $this->fail('role_id', 'El rol seleccionado no existe.');
            }
            if (in_array($role->slug, ['admin', 'super-admin'], true)) {
                $this->fail('role_id', 'Solo un administrador puede asignar el rol de administrador.');
            }

            $granted = $role->permissions->pluck('slug')->all();
            $owned = $actor->roles()->with('permissions')->get()
                ->flatMap(static fn (Role $role): array => $role->permissions->pluck('slug')->all())
                ->unique()
                ->all();

            if (array_diff($granted, $owned) !== []) {
                $this->fail('role_id', 'No puedes asignar un rol con permisos que no posees.');
            }
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
        $actor = $this->actor();
        if ($actor instanceof User && (int) $actor->getKey() === (int) $model->getKey()) {
            $this->fail('error', 'No puedes eliminar tu propia cuenta en sesión activa.');
        }
    }

    private function actor(): ?User
    {
        $actor = Auth::user();
        if ($actor instanceof User) {
            return $actor;
        }

        $context = app(ExecutionContext::class)->actor;

        return $context !== null ? app(CapabilityAuthorizer::class)->user($context) : null;
    }
}
