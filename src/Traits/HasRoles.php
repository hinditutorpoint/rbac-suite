<?php

namespace RbacSuite\OmniAccess\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Models\Role;
use RbacSuite\OmniAccess\Services\CacheService;
use RbacSuite\OmniAccess\Exceptions\GuardNotFoundException;

trait HasRoles
{
    use HasGuards;

    /**
     * Boot the trait
     */
    public static function bootHasRoles(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $cache = app(CacheService::class);
            $cache->forgetUser($model->getKey());

            $model->roles()->detach();
        });
    }

    /**
     * Roles relationship
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('omni-access.table_names.role_user'),
            config('omni-access.column_names.user_pivot_key'),
            config('omni-access.column_names.role_pivot_key')
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Role Assignment Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Assign roles to user
     * 
     * @param  mixed  ...$roles  Role models, slugs, names, or arrays
     * @return self
     */
    public function assignRole(...$roles): self
    {
        $roles = $this->parseRoles($roles);

        if ($roles->isEmpty()) {
            return $this;
        }

        $this->roles()->syncWithoutDetaching($roles->pluck('id')->toArray());
        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Remove roles from user
     * 
     * @param  mixed  ...$roles  Role models, slugs, names, or arrays
     * @return self
     */
    public function removeRole(...$roles): self
    {
        $roles = $this->parseRoles($roles);

        if ($roles->isEmpty()) {
            return $this;
        }

        $this->roles()->detach($roles->pluck('id')->toArray());
        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Sync roles (replace all)
     * 
     * @param  mixed  ...$roles  Role models, slugs, names, or arrays
     * @return self
     */
    public function syncRoles(...$roles): self
    {
        $roles = $this->parseRoles($roles);

        $this->roles()->sync($roles->pluck('id')->toArray());
        $this->forgetPermissionCache();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Role Checking Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if user has role(s)
     * 
     * @param  string|array|Role  $roles
     * @param  string|null  $guard
     * @return bool
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        $roles = $this->normalizeRoleInput($roles);
        $guard = $guard ?? $this->getGuardName();

        $userRoles = $this->getCachedRoles();

        foreach ($roles as $role) {
            $found = $userRoles->first(function ($r) use ($role, $guard) {
                $matchesIdentifier = $r->slug === $role || $r->name === $role || $r->id === $role;
                $matchesGuard = !config('omni-access.middleware.strict_guard') || $r->guard_name === $guard;
                
                return $matchesIdentifier && $matchesGuard;
            });

            if ($found) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given roles
     * 
     * @param  mixed  ...$roles
     * @return bool
     */
    public function hasAnyRole(...$roles): bool
    {
        $roles = collect($roles)->flatten()->toArray();
        
        // Extract guard if provided
        $guard = null;
        $roles = array_filter($roles, function ($role) use (&$guard) {
            if (is_string($role) && str_starts_with($role, 'guard:')) {
                $guard = substr($role, 6);
                return false;
            }
            return true;
        });

        return $this->hasRole($roles, $guard);
    }

    /**
     * Check if user has all given roles
     * 
     * @param  mixed  ...$roles
     * @return bool
     */
    public function hasAllRoles(...$roles): bool
    {
        $roles = collect($roles)->flatten()->toArray();

        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has exact roles (no more, no less)
     * 
     * @param  mixed  ...$roles
     * @return bool
     */
    public function hasExactRoles(...$roles): bool
    {
        $roles = $this->parseRoles($roles);
        $userRoles = $this->getCachedRoles();

        if ($roles->count() !== $userRoles->count()) {
            return false;
        }

        return $roles->pluck('id')->sort()->values()->toArray() 
            === $userRoles->pluck('id')->sort()->values()->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Checking Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if user has permission
     * 
     * @param  string|Permission  $permission
     * @return bool
     */
    public function hasPermission($permission): bool
    {
        // Super admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissionSlug = $permission instanceof Permission ? $permission->slug : $permission;

        return $this->getAllPermissions()->contains(function ($p) use ($permissionSlug) {
            return $p->slug === $permissionSlug || $p->name === $permissionSlug;
        });
    }

    /**
     * Check if user has any of the given permissions
     * 
     * @param  mixed  ...$permissions
     * @return bool
     */
    public function hasAnyPermission(...$permissions): bool
    {
        // Super admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = collect($permissions)->flatten()->toArray();

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all given permissions
     * 
     * @param  mixed  ...$permissions
     * @return bool
     */
    public function hasAllPermissions(...$permissions): bool
    {
        // Super admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = collect($permissions)->flatten()->toArray();

        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alias for hasPermission (Laravel Gate compatibility)
     */
    public function can($ability, $arguments = []): bool
    {
        // First check permission
        if ($this->hasPermission($ability)) {
            return true;
        }

        // Fallback to parent can() if available
        if (method_exists(parent::class, 'can')) {
            return parent::can($ability, $arguments);
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Retrieval Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get all permissions (cached)
     */
    public function getAllPermissions(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::getAllCached();
        }

        $cache = app(CacheService::class);

        return $cache->cacheUserPermissions($this->getKey(), function () {
            return $this->getPermissionsFromRoles();
        });
    }

    /**
     * Get permissions from user's roles
     */
    protected function getPermissionsFromRoles(): Collection
    {
        return $this->getCachedRoles()
            ->load('permissions')
            ->pluck('permissions')
            ->flatten()
            ->unique('id')
            ->values();
    }

    /**
     * Get permissions grouped by group
     */
    public function getGroupedPermissions(): Collection
    {
        return $this->getAllPermissions()
            ->load('group')
            ->groupBy(fn ($permission) => $permission->group?->name ?? 'Uncategorized');
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get cached roles
     */
    protected function getCachedRoles(): Collection
    {
        $cache = app(CacheService::class);

        return $cache->cacheUserRoles($this->getKey(), function () {
            return $this->roles()->get();
        });
    }

    /**
     * Forget permission cache
     */
    protected function forgetPermissionCache(): void
    {
        $cache = app(CacheService::class);
        $cache->forgetUser($this->getKey());
    }

    /**
     * Refresh permissions cache
     */
    public function refreshPermissionsCache(): self
    {
        $this->forgetPermissionCache();
        $this->getAllPermissions();
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        $superAdminRole = config('omni-access.super_admin_role', 'super-admin');
        
        return $this->getCachedRoles()->contains(function ($role) use ($superAdminRole) {
            return $role->slug === $superAdminRole || $role->name === $superAdminRole;
        });
    }

    /**
     * Parse role input into collection
     */
    protected function parseRoles(array $roles): Collection
    {
        return collect($roles)
            ->flatten()
            ->map(function ($role) {
                if ($role instanceof Role) {
                    return $role;
                }

                // Try to find by slug first, then by name
                return Role::findBySlugCached($role) 
                    ?? Role::where('name', $role)->first()
                    ?? Role::find($role);
            })
            ->filter()
            ->unique('id');
    }

    /**
     * Normalize role input to array
     */
    protected function normalizeRoleInput($roles): array
    {
        if ($roles instanceof Role) {
            return [$roles->slug];
        }

        if (is_string($roles)) {
            // Handle pipe-separated
            if (str_contains($roles, '|')) {
                return explode('|', $roles);
            }
            // Handle comma-separated
            if (str_contains($roles, ',')) {
                return explode(',', $roles);
            }
            return [$roles];
        }

        if (is_array($roles)) {
            return collect($roles)->flatten()->map(function ($role) {
                return $role instanceof Role ? $role->slug : $role;
            })->toArray();
        }

        return [];
    }

    /**
     * Get role names
     */
    public function getRoleNames(): Collection
    {
        return $this->getCachedRoles()->pluck('name');
    }

    /**
     * Get role slugs
     */
    public function getRoleSlugs(): Collection
    {
        return $this->getCachedRoles()->pluck('slug');
    }

    /**
     * Get permission names
     */
    public function getPermissionNames(): Collection
    {
        return $this->getAllPermissions()->pluck('name');
    }

    /**
     * Get permission slugs
     */
    public function getPermissionSlugs(): Collection
    {
        return $this->getAllPermissions()->pluck('slug');
    }
}