<?php

namespace RbacSuite\OmniAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RbacSuite\OmniAccess\Services\CacheService;
use RbacSuite\OmniAccess\Traits\HasPrimaryKeyType;
use RbacSuite\OmniAccess\Traits\MultiTenantAware;

class Role extends Model
{
    use HasPrimaryKeyType, MultiTenantAware, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_default',
        'tenant_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('omni-access-manager.table_names.roles', 'roles'));
    }

    protected static function boot()
    {
        parent::boot();

        // Register observer
        static::observe(\StoreLock\AdvancePermission\Observers\RoleObserver::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            config('omni-access-manager.table_names.permission_role'),
            config('omni-access-manager.column_names.role_pivot_key'),
            config('omni-access-manager.column_names.permission_pivot_key')
        )->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('omni-access-manager.user_model'),
            config('omni-access-manager.table_names.role_user'),
            config('omni-access-manager.column_names.role_pivot_key'),
            config('omni-access-manager.column_names.user_pivot_key')
        )->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->getCachedPermissions()->contains('slug', $permission);
    }

    public function getCachedPermissions()
    {
        $cache = app(CacheService::class);

        return $cache->cacheRolePermissions($this->id, function () {
            return $this->permissions()->get();
        });
    }

    public function givePermissionTo(...$permissions): self
    {
        $permissions = $this->getPermissions($permissions);

        if ($permissions->isEmpty()) {
            return $this;
        }

        $this->permissions()->syncWithoutDetaching($permissions);

        $this->forgetCachedPermissions();

        return $this;
    }

    public function revokePermissionTo(...$permissions): self
    {
        $permissions = $this->getPermissions($permissions);

        $this->permissions()->detach($permissions);

        $this->forgetCachedPermissions();

        return $this;
    }

    public function syncPermissions(...$permissions): self
    {
        $permissions = $this->getPermissions($permissions);

        $this->permissions()->sync($permissions);

        $this->forgetCachedPermissions();

        return $this;
    }

    protected function getPermissions(array $permissions): \Illuminate\Support\Collection
    {
        return collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                if ($permission instanceof Permission) {
                    return $permission;
                }

                return Permission::where('slug', $permission)->first();
            })
            ->filter()
            ->unique('id');
    }

    protected function forgetCachedPermissions(): void
    {
        $cache = app(CacheService::class);
        $cache->forgetRole($this->id);

        // Clear cache for all users with this role
        foreach ($this->users()->pluck('id') as $userId) {
            $cache->forgetUserPermissions($userId);
        }
    }

    /**
     * Get all roles with caching
     */
    public static function getAllCached()
    {
        $cache = app(CacheService::class);

        return $cache->cacheRoles(function () {
            return static::all();
        });
    }

    /**
     * Get all roles with permissions (cached)
     */
    public static function getAllWithPermissions()
    {
        $cache = app(CacheService::class);

        return $cache->cacheRolesWithPermissions(function () {
            return static::with('permissions')->get();
        });
    }

    /**
     * Find role by slug with caching
     */
    public static function findBySlugCached(string $slug): ?self
    {
        $cache = app(CacheService::class);

        return $cache->remember("role.slug.{$slug}", function () use ($slug) {
            return static::where('slug', $slug)->first();
        });
    }
}
