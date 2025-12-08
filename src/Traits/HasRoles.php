<?php

namespace RbacSuite\OmniAccess\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Models\Role;
use RbacSuite\OmniAccess\Services\CacheService;

trait HasRoles
{
    public static function bootHasRoles(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $cache = app(CacheService::class);
            $cache->forgetUserPermissions($model->getKey());

            $model->roles()->detach();
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('omni-access-manager.table_names.role_user'),
            config('omni-access-manager.column_names.user_pivot_key'),
            config('omni-access-manager.column_names.role_pivot_key')
        )->withTimestamps();
    }

    public function assignRole(...$roles): self
    {
        $roles = $this->getRoles($roles);

        $this->roles()->syncWithoutDetaching($roles);

        $this->forgetPermissionCache();

        return $this;
    }

    public function removeRole(...$roles): self
    {
        $roles = $this->getRoles($roles);

        $this->roles()->detach($roles);

        $this->forgetPermissionCache();

        return $this;
    }

    public function syncRoles(...$roles): self
    {
        $roles = $this->getRoles($roles);

        $this->roles()->sync($roles);

        $this->forgetPermissionCache();

        return $this;
    }

    public function hasRole($role, $requireAll = false): bool
    {
        if (is_array($role)) {
            if ($requireAll) {
                foreach ($role as $r) {
                    if (! $this->hasRole($r)) {
                        return false;
                    }
                }

                return true;
            } else {
                foreach ($role as $r) {
                    if ($this->hasRole($r)) {
                        return true;
                    }
                }

                return false;
            }
        }

        return $this->getCachedRoles()->contains(function ($r) use ($role) {
            return $r->slug === $role || $r->name === $role;
        });
    }

    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    public function hasAllRoles(...$roles): bool
    {
        return $this->hasRole($roles, true);
    }

    public function hasPermission($permission): bool
    {
        if ($this->hasRole(config('omni-access-manager.super_admin_role'))) {
            return true;
        }

        return $this->getAllPermissions()->contains('slug', $permission);
    }

    public function hasAnyPermission(...$permissions): bool
    {
        if ($this->hasRole(config('omni-access-manager.super_admin_role'))) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(...$permissions): bool
    {
        if ($this->hasRole(config('omni-access-manager.super_admin_role'))) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function getAllPermissions(): Collection
    {
        if ($this->hasRole(config('omni-access-manager.super_admin_role'))) {
            return Permission::getAllCached();
        }

        $cache = app(CacheService::class);

        return $cache->cacheUserPermissions($this->getKey(), function () {
            return $this->getPermissionsFromRoles();
        });
    }

    protected function getCachedRoles(): Collection
    {
        $cache = app(CacheService::class);

        return $cache->remember("user.{$this->getKey()}.roles", function () {
            return $this->roles()->get();
        });
    }

    protected function getPermissionsFromRoles(): Collection
    {
        return $this->getCachedRoles()
            ->load('permissions')
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    protected function getRoles(array $roles): Collection
    {
        return collect($roles)
            ->flatten()
            ->map(function ($role) {
                if ($role instanceof Role) {
                    return $role;
                }

                return Role::findBySlugCached($role)
                    ?? Role::where('name', $role)->first();
            })
            ->filter()
            ->unique('id');
    }

    protected function forgetPermissionCache(): void
    {
        $cache = app(CacheService::class);
        $cache->forgetUserPermissions($this->getKey());
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('omni-access-manager.super_admin_role'));
    }

    /**
     * Get permissions grouped by group
     */
    public function getGroupedPermissions(): Collection
    {
        return $this->getAllPermissions()
            ->load('group')
            ->groupBy('group.name');
    }

    /**
     * Refresh user permissions cache
     */
    public function refreshPermissionsCache(): void
    {
        $this->forgetPermissionCache();
        $this->getAllPermissions();
    }
}
