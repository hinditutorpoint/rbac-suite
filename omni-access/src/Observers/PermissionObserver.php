<?php

namespace RbacSuite\OmniAccess\Observers;

use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Services\CacheService;

class PermissionObserver
{
    protected CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }

    public function created(Permission $permission): void
    {
        $this->clearCache($permission);
    }

    public function updated(Permission $permission): void
    {
        $this->clearCache($permission);
    }

    public function deleted(Permission $permission): void
    {
        $this->clearCache($permission);
    }

    protected function clearCache(Permission $permission): void
    {
        $this->cache->forgetPermission($permission->id);
        $this->cache->forget('permissions.all');
        $this->cache->forget("permission.slug.{$permission->slug}");
        
        // Clear cache for all roles with this permission
        foreach ($permission->roles as $role) {
            $this->cache->forgetRole($role->id);
        }
    }
}