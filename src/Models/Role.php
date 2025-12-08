<?php

namespace RbacSuite\OmniAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RbacSuite\OmniAccess\Services\CacheService;
use RbacSuite\OmniAccess\Traits\HasPrimaryKeyType;

class Role extends Model
{
    use HasPrimaryKeyType, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'guard_name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('omni-access.user_model'),
            config('omni-access.table_names.role_user'),
            config('omni-access.column_names.role_pivot_key'),
            config('omni-access.column_names.user_pivot_key')
        )->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->getCachedPermissions()->contains('slug', $permission);
    }

    public function getCachedPermissions()
    {
        $cache = app(CacheService::class);
        
        return $cache->remember("role.{$this->id}.permissions", function () {
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
            $cache->forgetUser($userId);
        }
    }

    public static function getAllCached()
    {
        $cache = app(CacheService::class);
        
        return $cache->remember('roles.all', function () {
            return static::all();
        });
    }

    public static function findBySlugCached(string $slug): ?self
    {
        $cache = app(CacheService::class);
        
        return $cache->remember("role.slug.{$slug}", function () use ($slug) {
            return static::where('slug', $slug)->first();
        });
    }

    public static function getDefaultRole(): ?self
    {
        return static::where('is_default', true)->first();
    }
}