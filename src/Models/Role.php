<?php

namespace RbacSuite\OmniAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RbacSuite\OmniAccess\Services\CacheService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use RbacSuite\OmniAccess\Traits\HasActiveStatus;

class Role extends Model
{
    use HasUuids, SoftDeletes, HasActiveStatus;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'icon',
        'guard_name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_default' => true,
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('omni-access.table_names.roles', 'roles'));
    }

    protected static function boot()
    {
        parent::boot();
        static::observe(\RbacSuite\OmniAccess\Observers\RoleObserver::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            config('omni-access.table_names.permission_role'),
            config('omni-access.column_names.role_pivot_key'),
            config('omni-access.column_names.permission_pivot_key')
        )->withTimestamps();
    }

    /**
     * Active permissions only
     */
    public function activePermissions(): BelongsToMany
    {
        return $this->permissions()->where('is_active', true);
    }

    /**
     * Users relationship
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('omni-access.user_model'),
            config('omni-access.table_names.role_user'),
            config('omni-access.column_names.role_pivot_key'),
            config('omni-access.column_names.user_pivot_key')
        )->withTimestamps();
    }

    /**
     * Check if role has permission
     */
    public function hasPermission(string $permission): bool
    {
        return $this->getCachedPermissions()->contains('slug', $permission);
    }

    /**
     * Get cached permissions (active only)
     */
    public function getCachedPermissions()
    {
        $cache = app(CacheService::class);

        return $cache->remember("role.{$this->id}.permissions.active", function () {
            return $this->activePermissions()->get();
        });
    }

    /**
     * Get all cached permissions including inactive
     */
    public function getAllCachedPermissions()
    {
        $cache = app(CacheService::class);

        return $cache->remember("role.{$this->id}.permissions.all", function () {
            return $this->permissions()->withoutGlobalScope('active')->get();
        });
    }

    /**
     * Give permission to role
     */
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

    /**
     * Revoke permission from role
     */
    public function revokePermissionTo(...$permissions): self
    {
        $permissions = $this->getPermissions($permissions);
        $this->permissions()->detach($permissions);
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Sync permissions
     */
    public function syncPermissions(...$permissions): self
    {
        $permissions = $this->getPermissions($permissions);
        $this->permissions()->sync($permissions);
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Parse permissions input
     */
    protected function getPermissions(array $permissions): \Illuminate\Support\Collection
    {
        return collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                if ($permission instanceof Permission) {
                    return $permission;
                }

                return Permission::withInactive()
                    ->where('slug', $permission)
                    ->orWhere('name', $permission)
                    ->first();
            })
            ->filter()
            ->unique('id');
    }

    /**
     * Forget cached permissions
     */
    protected function forgetCachedPermissions(): void
    {
        $cache = app(CacheService::class);
        $cache->forgetRole($this->id);
        $cache->forget("role.{$this->id}.permissions.active");
        $cache->forget("role.{$this->id}.permissions.all");

        // Clear cache for all users with this role
        foreach ($this->users()->pluck('id') as $userId) {
            $cache->forgetUser($userId);
        }
    }

    /**
     * Clear status cache
     */
    protected function clearStatusCache(): void
    {
        $cache = app(CacheService::class);
        $cache->forgetRoles();
        $cache->forget('roles.all.active');
        $cache->forget('roles.all.with_inactive');
        $cache->forget("role.slug.{$this->slug}");

        // Clear cache for all users with this role
        foreach ($this->users()->pluck('id') as $userId) {
            $cache->forgetUser($userId);
        }
    }

    /**
     * Get all cached (active only)
     */
    public static function getAllCached()
    {
        $cache = app(CacheService::class);

        return $cache->remember('roles.all.active', function () {
            return static::all();
        });
    }

    /**
     * Get all including inactive
     */
    public static function getAllWithInactiveCached()
    {
        $cache = app(CacheService::class);

        return $cache->remember('roles.all.with_inactive', function () {
            return static::withInactive()->get();
        });
    }

    /**
     * Find by slug (cached, active only)
     */
    public static function findBySlugCached(string $slug): ?self
    {
        $cache = app(CacheService::class);

        return $cache->remember("role.slug.{$slug}", function () use ($slug) {
            return static::where('slug', $slug)->first();
        });
    }

    /**
     * Find by slug including inactive
     */
    public static function findBySlugWithInactive(string $slug): ?self
    {
        return static::withInactive()->where('slug', $slug)->first();
    }

    /**
     * Get default role
     */
    public static function getDefaultRole(): ?self
    {
        return static::where('is_default', true)->first();
    }

    public static function getDefaultRoleCached(): ?self
    {
        $cache = app(CacheService::class);
        
        return $cache->remember('roles.default', function () {
            return static::getDefaultRole();
        });
    }

    /**
     * Scope a query to only include popular groups, ordered by the number of permissions they have.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePopular(Builder $query) : Builder
    {
        return $query->withCount('permissions')->orderBy('permissions_count', 'desc');
    }

    /**
     * Scope a query to order the groups by their sort order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered(Builder $query) : Builder
    {
        return $query->orderBy('name');
    }

}