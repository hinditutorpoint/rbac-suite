<?php

namespace RbacSuite\OmniAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use RbacSuite\OmniAccess\Traits\HasActiveStatus;
use RbacSuite\OmniAccess\Services\CacheService;

class Permission extends Model
{
    use HasUuids, SoftDeletes, HasActiveStatus;

    protected $fillable = [
        'name',
        'slug',
        'guard_name',
        'description',
        'group_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('omni-access.table_names.permissions', 'permissions'));
    }

    protected static function boot()
    {
        parent::boot();
        static::observe(\RbacSuite\OmniAccess\Observers\PermissionObserver::class);
    }

    /**
     * Permission belongs to a group
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    /**
     * Active group only
     */
    public function activeGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id')
            ->where('is_active', true);
    }

    /**
     * Roles that have this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('omni-access.table_names.permission_role'),
            config('omni-access.column_names.permission_pivot_key'),
            config('omni-access.column_names.role_pivot_key')
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
     * Users that have this permission directly
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('omni-access.user_model'),
            config('omni-access.table_names.permission_user', 'permission_user'),
            config('omni-access.column_names.permission_pivot_key', 'permission_id'),
            config('omni-access.column_names.user_pivot_key', 'user_id')
        )->withTimestamps();
    }

    /**
     * Clear status cache
     */
    protected function clearStatusCache(): void
    {
        $cache = app(CacheService::class);
        $cache->forgetPermissions();
        $cache->forget('permissions.all.active');
        $cache->forget('permissions.all.with_inactive');
        $cache->forget("permission.slug.{$this->slug}");

        // Clear cache for all roles with this permission
        foreach ($this->roles()->withoutGlobalScope('active')->pluck('id') as $roleId) {
            $cache->forgetRole($roleId);
        }

        // Clear cache for all users with this direct permission
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

        return $cache->remember('permissions.all.active', function () {
            return static::with(['group' => function ($query) {
                $query->where('is_active', true);
            }])->get();
        });
    }

    /**
     * Get all including inactive
     */
    public static function getAllWithInactiveCached()
    {
        $cache = app(CacheService::class);

        return $cache->remember('permissions.all.with_inactive', function () {
            return static::withInactive()->with('group')->get();
        });
    }

    /**
     * Find by slug (cached, active only)
     */
    public static function findBySlugCached(string $slug): ?self
    {
        $cache = app(CacheService::class);

        return $cache->remember("permission.slug.{$slug}", function () use ($slug) {
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
     * Check if permission is fully active (permission + group)
     */
    public function isFullyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check if group is active (if has group)
        if ($this->group_id) {
            $group = Group::withInactive()->find($this->group_id);
            return $group && $group->is_active;
        }

        return true;
    }

    /**
     * Scope to filter by group
     */
    public function scopeInGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Get formatted name
     */
    public function getFormattedNameAttribute()
    {
        return ucfirst(str_replace('.', ' ', $this->slug));
    }

    /**
     * Scope for ordering
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}