<?php

declare(strict_types=1);

namespace DbCms\Core\Traits;

use DbCms\Core\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRolesAndPermissions
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /** @param string|list<string> $roles */
    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : [$roles];

        return $this->roles()->whereIn('slug', $roleList)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(['admin', 'super-admin']);
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->roles()->whereHas('permissions', function ($q) use ($permissionSlug): void {
            $q->where('slug', $permissionSlug);
        })->exists();
    }

    public function assignRole(string|Role $role): void
    {
        $roleModel = is_string($role) ? Role::where('slug', $role)->firstOrFail() : $role;
        $this->roles()->syncWithoutDetaching([$roleModel->id]);
    }
}
