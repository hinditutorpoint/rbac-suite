<?php

namespace RbacSuite\OmniAccess\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RbacSuite\OmniAccess\Traits\HasPrimaryKeyType;

class Group extends Model
{
    use HasPrimaryKeyType;

    protected $fillable = [
        'name',
        'slug',
        'guard_name',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('omni-access.table_names.groups', 'permission_groups'));
    }

    protected static function boot()
    {
        parent::boot();

        static::observe(\RbacSuite\OmniAccess\Observers\GroupObserver::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'group_id')->orderBy('name');
    }

    public static function getAllCached()
    {
        $cache = app(\RbacSuite\OmniAccess\Services\CacheService::class);
        
        return $cache->remember('groups.all', function () {
            return static::with('permissions')->orderBy('sort_order')->get();
        });
    }
}