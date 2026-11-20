<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Security;

use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Support\Facades\DB;

/**
 * Idempotently creates the permission catalogue and default roles.
 *
 * Existing roles keep custom permissions: the provisioner only adds the
 * documented defaults. Run it on a clean install and after installing modules.
 */
final class AccessProvisioner
{
    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * @return array{permissions:int, roles:int, assignments:int}
     */
    public function provision(): array
    {
        $definitions = PermissionCatalog::definitions($this->modules->discoverModules());
        $roles = PermissionCatalog::roles($definitions);

        return DB::transaction(function () use ($definitions, $roles): array {
            $permissions = [];
            foreach ($definitions as $slug => $name) {
                $permissions[$slug] = Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name],
                );
            }

            $assignments = 0;
            foreach ($roles as $slug => $definition) {
                $role = Role::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $definition['name'], 'description' => $definition['description']],
                );

                $ids = [];
                foreach ($definition['permissions'] as $permissionSlug) {
                    if (isset($permissions[$permissionSlug])) {
                        $ids[] = $permissions[$permissionSlug]->getKey();
                    }
                }
                $ids = array_values(array_unique($ids));
                $role->permissions()->syncWithoutDetaching($ids);
                $assignments += count($ids);
            }

            return [
                'permissions' => count($permissions),
                'roles' => count($roles),
                'assignments' => $assignments,
            ];
        });
    }
}
