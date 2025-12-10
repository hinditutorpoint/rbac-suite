<?php

namespace RbacSuite\OmniAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Permission extends Model
{
    use HasUuids, SoftDeletes;

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

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            Group::class,
            'group_id'
        );
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('omni-access.table_names.permission_role'),
            config('omni-access.column_names.permission_pivot_key'),
            config('omni-access.column_names.role_pivot_key')
        )->withTimestamps();
    }

    public static function getAllCached()
    {
        $cache = app(\RbacSuite\OmniAccess\Services\CacheService::class);
        
        return $cache->remember('permissions.all', function () {
            return static::with('group')->get();
        });
    }

    public static function findBySlugCached(string $slug): ?self
    {
        $cache = app(\RbacSuite\OmniAccess\Services\CacheService::class);
        
        return $cache->remember("permission.slug.{$slug}", function () use ($slug) {
            return static::where('slug', $slug)->first();
        });
    }

    /**
     * Scope for active groups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}