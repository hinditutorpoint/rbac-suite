<?php

namespace RbacSuite\OmniAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use RbacSuite\OmniAccess\Traits\HasActiveStatus;
use RbacSuite\OmniAccess\Services\CacheService;

class Group extends Model
{
    use HasUuids, HasActiveStatus;

    protected $fillable = [
        'name',
        'slug',
        'guard_name',
        'description',
        'color',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('omni-access.table_names.groups', 'permission_groups'));
    }

    public function getTable() { return config('omni-access.table_names.groups', 'permission_groups'); }

    protected static function boot()
    {
        parent::boot();

        static::observe(\RbacSuite\OmniAccess\Observers\GroupObserver::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'group_id')->orderBy('name');
    }

    /**
     * Active permissions in this group
     */
    public function activePermissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'group_id')
            ->where('is_active', true)
            ->orderBy('name');
    }

    public static function getAllCached()
    {
        $cache = app(CacheService::class);

        return $cache->remember('groups.all.active', function () {
            return static::with(['permissions' => function ($query) {
                $query->where('is_active', true);
            }])->orderBy('sort_order')->get();
        });
    }

    /**
     * Get all including inactive
     */
    public static function getAllWithInactiveCached()
    {
        $cache = app(CacheService::class);

        return $cache->remember('groups.all.with_inactive', function () {
            return static::withInactive()
                ->with('permissions')
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Clear status cache
     */
    protected function clearStatusCache(): void
    {
        $cache = app(CacheService::class);
        $cache->forgetGroups();
        $cache->forget('groups.all.active');
        $cache->forget('groups.all.with_inactive');
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
        return $query->orderBy('sort_order')->orderBy('name');
    }
}