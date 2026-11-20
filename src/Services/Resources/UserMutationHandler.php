<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Resources;

use DbCms\Core\Capabilities\CapabilityAuthorizer;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Privacy\AccountPrivacyService;
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
        if (! $model instanceof User) {
            throw new \LogicException('Expected a user.');
        }
        $actor = $this->actor();
        if (! $creating) {
            $this->assertCanManage($model, $actor);
        }

        if (isset($attributes['email'])) {
            $attributes['email'] = strtolower(trim((string) $attributes['email']));
        }
        if (! $creating && array_key_exists('role_id', $attributes) && App::isProduction() && (bool) config('cms.lock_roles_in_production', true)) {
            $this->fail('error', 'La modificación de roles está bloqueada en entorno de producción por directivas de seguridad RBAC.');
        }

        if (! array_key_exists('role_id', $attributes)) {
            return $attributes;
        }
        $role = Role::with('permissions')->find((int) $attributes['role_id']);
        if (! $role instanceof Role) {
            $this->fail('role_id', 'El rol seleccionado no existe.');
        }

        $adminRole = in_array($role->slug, ['admin', 'super-admin'], true);
        if (! $actor?->isAdmin() || $actor->is_service_account) {
            if (! $creating && $actor?->is($model)) {
                $this->fail('role_id', 'No puedes cambiar tu propio rol.');
            }
            if ($adminRole || $actor === null
                || array_diff($role->permissions->pluck('slug')->all(), $this->permissions($actor)) !== []) {
                $this->fail('role_id', 'No puedes asignar un rol con permisos que no posees.');
            }
        }
        if (! $creating && ! $adminRole) {
            app(AccountPrivacyService::class)->assertAnotherHumanAdministrator($model);
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
        $this->assertCanManage($model, $actor);
        if ($actor?->is($model)) {
            $this->fail('error', 'No puedes eliminar tu propia cuenta en sesión activa.');
        }
    }

    private function assertCanManage(User $target, ?User $actor): void
    {
        if ($target->is_service_account) {
            $this->fail('user', 'Gestiona esta identidad desde el ciclo de vida de cuentas de servicio.');
        }
        if ($actor === null || ($actor->is_service_account || ! $actor->isAdmin())
            && ($target->isAdmin() || array_diff($this->permissions($target), $this->permissions($actor)) !== [])) {
            $this->fail('user', 'No puedes modificar ni eliminar una cuenta con más privilegios.');
        }
    }

    /** @return list<string> */
    private function permissions(User $user): array
    {
        return array_values($user->roles()->with('permissions')->get()
            ->flatMap(static fn (Role $role): array => $role->permissions->map(static fn (Permission $permission): string => $permission->slug)->all())
            ->unique()->all());
    }

    private function actor(): ?User
    {
        $context = app(ExecutionContext::class)->actor;
        if ($context !== null) {
            return app(CapabilityAuthorizer::class)->user($context);
        }
        $actor = Auth::user();

        return $actor instanceof User ? $actor : null;
    }
}
