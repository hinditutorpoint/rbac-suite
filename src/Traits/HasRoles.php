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
            $model->permissions()->detach();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Roles relationship
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('omni-access.table_names.role_user', 'role_user'),
            config('omni-access.column_names.user_pivot_key', 'user_id'),
            config('omni-access.column_names.role_pivot_key', 'role_id')
        )->withTimestamps();
    }

    /**
     * Active roles only
     */
    public function activeRoles(): BelongsToMany
    {
        return $this->roles()->where('is_active', true);
    }

    /**
     * Direct permissions relationship
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            config('omni-access.table_names.permission_user', 'permission_user'),
            config('omni-access.column_names.user_pivot_key', 'user_id'),
            config('omni-access.column_names.permission_pivot_key', 'permission_id')
        )->withTimestamps();
    }

    /**
     * Active direct permissions only
     */
    public function activePermissions(): BelongsToMany
    {
        return $this->permissions()->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Role Assignment Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Assign roles to user
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
    | Direct Permission Assignment Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Give direct permission(s) to user
     */
    public function givePermissionTo(...$permissions): self
    {
        $permissions = $this->parsePermissions($permissions);

        if ($permissions->isEmpty()) {
            return $this;
        }

        $this->permissions()->syncWithoutDetaching($permissions->pluck('id')->toArray());
        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Revoke direct permission(s) from user
     */
    public function revokePermissionTo(...$permissions): self
    {
        $permissions = $this->parsePermissions($permissions);

        if ($permissions->isEmpty()) {
            return $this;
        }

        $this->permissions()->detach($permissions->pluck('id')->toArray());
        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Sync direct permissions (replace all)
     */
    public function syncPermissions(...$permissions): self
    {
        $permissions = $this->parsePermissions($permissions);

        $this->permissions()->sync($permissions->pluck('id')->toArray());
        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Revoke all direct permissions
     */
    public function revokeAllPermissions(): self
    {
        $this->permissions()->detach();
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
     * Only checks ACTIVE roles by default
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        $roles = $this->normalizeRoleInput($roles);
        $guard = $guard ?? $this->getGuardName();

        $userRoles = $this->getCachedRoles();

        foreach ($roles as $role) {
            $found = $userRoles->first(function ($r) use ($role, $guard) {
                // Check if role matches identifier
                $matchesIdentifier = $r->slug === $role || $r->name === $role || $r->id === $role;
                
                // Check guard
                $matchesGuard = !config('omni-access.middleware.strict_guard') || $r->guard_name === $guard;
                
                // Check if role is active (if status check enabled)
                $isActive = !config('omni-access.status.check_role_status', true) || $r->is_active;

                return $matchesIdentifier && $matchesGuard && $isActive;
            });

            if ($found) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has role (including inactive)
     */
    public function hasRoleIncludingInactive($roles, ?string $guard = null): bool
    {
        $roles = $this->normalizeRoleInput($roles);
        $guard = $guard ?? $this->getGuardName();

        $userRoles = $this->getAllCachedRoles(); // Includes inactive

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
     * Check if user has exact roles
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
     * Check if user has permission (via role OR direct)
     * Only checks ACTIVE permissions and ACTIVE roles by default
     */
    public function hasPermission($permission): bool
    {
        // Super admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissionSlug = $permission instanceof Permission ? $permission->slug : $permission;

        return $this->getAllPermissions()->contains(function ($p) use ($permissionSlug) {
            // Check permission matches
            $matches = $p->slug === $permissionSlug || $p->name === $permissionSlug;
            
            // Check if permission is active
            $isActive = !config('omni-access.status.filter_inactive', true) || $p->is_active;
            
            // Check if group is active (if status check enabled)
            $groupActive = true;
            if (config('omni-access.status.check_group_status', true) && $p->group_id) {
                $groupActive = $p->group && $p->group->is_active;
            }

            return $matches && $isActive && $groupActive;
        });
    }

    /**
     * Check if user has permission (including inactive)
     */
    public function hasPermissionIncludingInactive($permission): bool
    {
        // Super admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissionSlug = $permission instanceof Permission ? $permission->slug : $permission;

        return $this->getAllPermissionsIncludingInactive()->contains(function ($p) use ($permissionSlug) {
            return $p->slug === $permissionSlug || $p->name === $permissionSlug;
        });
    }

    /**
     * Check if user has direct permission
     */
    public function hasDirectPermission($permission): bool
    {
        $permissionSlug = $permission instanceof Permission ? $permission->slug : $permission;

        return $this->getDirectPermissions()->contains(function ($p) use ($permissionSlug) {
            $matches = $p->slug === $permissionSlug || $p->name === $permissionSlug;
            $isActive = !config('omni-access.status.filter_inactive', true) || $p->is_active;

            return $matches && $isActive;
        });
    }

    /**
     * Check if user has permission via role
     */
    public function hasPermissionViaRole($permission): bool
    {
        // Super admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissionSlug = $permission instanceof Permission ? $permission->slug : $permission;

        return $this->getPermissionsViaRoles()->contains(function ($p) use ($permissionSlug) {
            $matches = $p->slug === $permissionSlug || $p->name === $permissionSlug;
            $isActive = !config('omni-access.status.filter_inactive', true) || $p->is_active;

            return $matches && $isActive;
        });
    }

    /**
     * Check if user has any of the given permissions
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
     * Check if user has any direct permission
     */
    public function hasAnyDirectPermission(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten()->toArray();

        foreach ($permissions as $permission) {
            if ($this->hasDirectPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all direct permissions
     */
    public function hasAllDirectPermissions(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten()->toArray();

        foreach ($permissions as $permission) {
            if (!$this->hasDirectPermission($permission)) {
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
     * Get all permissions (active only, direct + via roles)
     */
    public function getAllPermissions(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::getAllCached();
        }

        $cache = app(CacheService::class);

        return $cache->cacheUserPermissions($this->getKey(), function () {
            $directPermissions = $this->getDirectPermissions();
            $rolePermissions = $this->getPermissionsViaRoles();

            return $directPermissions->merge($rolePermissions)
                ->filter(function ($permission) {
                    // Filter by active status if enabled
                    if (config('omni-access.status.filter_inactive', true)) {
                        if (!$permission->is_active) {
                            return false;
                        }
                        
                        // Check group status
                        if (config('omni-access.status.check_group_status', true) && $permission->group_id) {
                            return $permission->group && $permission->group->is_active;
                        }
                    }
                    return true;
                })
                ->unique('id')
                ->values();
        });
    }

    /**
     * Get all permissions including inactive
     */
    public function getAllPermissionsIncludingInactive(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::getAllWithInactiveCached();
        }

        $cache = app(CacheService::class);

        return $cache->remember("user.{$this->getKey()}.permissions.all", function () {
            $directPermissions = $this->permissions()->withoutGlobalScope('active')->get();
            $rolePermissions = $this->getAllCachedRoles()
                ->load(['permissions' => function ($query) {
                    $query->withoutGlobalScope('active');
                }])
                ->pluck('permissions')
                ->flatten();

            return $directPermissions->merge($rolePermissions)->unique('id')->values();
        });
    }

    /**
     * Get direct permissions only (active)
     */
    public function getDirectPermissions(): Collection
    {
        $cache = app(CacheService::class);

        return $cache->remember("user.{$this->getKey()}.direct_permissions.active", function () {
            $query = $this->permissions();
            
            if (config('omni-access.status.filter_inactive', true)) {
                $query->where('is_active', true);
            }
            
            return $query->get();
        });
    }

    /**
     * Get direct permissions including inactive
     */
    public function getDirectPermissionsIncludingInactive(): Collection
    {
        $cache = app(CacheService::class);

        return $cache->remember("user.{$this->getKey()}.direct_permissions.all", function () {
            return $this->permissions()->withoutGlobalScope('active')->get();
        });
    }

    /**
     * Get permissions via roles (active only)
     */
    public function getPermissionsViaRoles(): Collection
    {
        return $this->getCachedRoles()
            ->filter(function ($role) {
                // Only include active roles if status check enabled
                return !config('omni-access.status.check_role_status', true) || $role->is_active;
            })
            ->load(['permissions' => function ($query) {
                if (config('omni-access.status.filter_inactive', true)) {
                    $query->where('is_active', true);
                }
            }])
            ->pluck('permissions')
            ->flatten()
            ->unique('id')
            ->values();
    }

    /**
     * Get permissions by source
     */
    public function getPermissionsBySource(): array
    {
        return [
            'direct' => $this->getDirectPermissions(),
            'via_roles' => $this->getPermissionsViaRoles(),
        ];
    }

    /**
     * Get permissions grouped by group
     */
    public function getGroupedPermissions(): Collection
    {
        return $this->getAllPermissions()
            ->load('group')
            ->groupBy(fn($permission) => $permission->group?->name ?? 'Uncategorized');
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get cached roles (active only)
     */
    protected function getCachedRoles(): Collection
    {
        $cache = app(CacheService::class);

        return $cache->cacheUserRoles($this->getKey(), function () {
            $query = $this->roles();
            
            if (config('omni-access.status.check_role_status', true)) {
                $query->where('is_active', true);
            }
            
            return $query->get();
        });
    }

    /**
     * Get all cached roles including inactive
     */
    protected function getAllCachedRoles(): Collection
    {
        $cache = app(CacheService::class);

        return $cache->remember("user.{$this->getKey()}.roles.all", function () {
            return $this->roles()->withoutGlobalScope('active')->get();
        });
    }

    /**
     * Forget permission cache
     */
    protected function forgetPermissionCache(): void
    {
        $cache = app(CacheService::class);
        $cache->forgetUser($this->getKey());
        $cache->forget("user.{$this->getKey()}.direct_permissions.active");
        $cache->forget("user.{$this->getKey()}.direct_permissions.all");
        $cache->forget("user.{$this->getKey()}.permissions.all");
        $cache->forget("user.{$this->getKey()}.roles.all");
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

        if (!$superAdminRole) {
            return false;
        }

        // Check in all roles (including inactive super-admin role? Usually not)
        return $this->getCachedRoles()->contains(function ($role) use ($superAdminRole) {
            return ($role->slug === $superAdminRole || $role->name === $superAdminRole) && $role->is_active;
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

                // Find including inactive for assignment purposes
                return Role::findBySlugWithInactive($role)
                    ?? Role::withInactive()->where('name', $role)->first()
                    ?? Role::withInactive()->find($role);
            })
            ->filter()
            ->unique('id');
    }

    /**
     * Parse permission input into collection
     */
    protected function parsePermissions(array $permissions): Collection
    {
        return collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                if ($permission instanceof Permission) {
                    return $permission;
                }

                // Find including inactive for assignment purposes
                return Permission::findBySlugWithInactive($permission)
                    ?? Permission::withInactive()->where('name', $permission)->first()
                    ?? Permission::withInactive()->find($permission);
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
            if (str_contains($roles, '|')) {
                return explode('|', $roles);
            }
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

    /*
    |--------------------------------------------------------------------------
    | Getter Methods
    |--------------------------------------------------------------------------
    */

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
     * Get all role names including inactive
     */
    public function getAllRoleNames(): Collection
    {
        return $this->getAllCachedRoles()->pluck('name');
    }

    /**
     * Get all role slugs including inactive
     */
    public function getAllRoleSlugs(): Collection
    {
        return $this->getAllCachedRoles()->pluck('slug');
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

    /**
     * Get direct permission names
     */
    public function getDirectPermissionNames(): Collection
    {
        return $this->getDirectPermissions()->pluck('name');
    }

    /**
     * Get direct permission slugs
     */
    public function getDirectPermissionSlugs(): Collection
    {
        return $this->getDirectPermissions()->pluck('slug');
    }

    /*
    |--------------------------------------------------------------------------
    | Status Check Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get inactive roles assigned to user
     */
    public function getInactiveRoles(): Collection
    {
        return $this->getAllCachedRoles()->filter(fn($role) => !$role->is_active);
    }

    /**
     * Get inactive permissions (direct)
     */
    public function getInactiveDirectPermissions(): Collection
    {
        return $this->getDirectPermissionsIncludingInactive()->filter(fn($p) => !$p->is_active);
    }

    /**
     * Check if user has any inactive roles
     */
    public function hasInactiveRoles(): bool
    {
        return $this->getInactiveRoles()->isNotEmpty();
    }

    /**
     * Check if user has any inactive direct permissions
     */
    public function hasInactiveDirectPermissions(): bool
    {
        return $this->getInactiveDirectPermissions()->isNotEmpty();
    }
}