<?php

namespace RbacSuite\OmniAccess\Observers;

use RbacSuite\OmniAccess\Models\Role;
use RbacSuite\OmniAccess\Services\CacheService;

class RoleObserver
{
    protected CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }

    public function created(Role $role): void
    {
        $this->clearCache($role);
    }

    public function updated(Role $role): void
    {
        $this->clearCache($role);
    }

    public function deleted(Role $role): void
    {
        $this->clearCache($role);
    }

    protected function clearCache(Role $role): void
    {
        $this->cache->forgetRole($role->id);
        $this->cache->forget('roles.all');
        $this->cache->forget("role.slug.{$role->slug}");
        
        // Clear cache for all users with this role
        foreach ($role->users as $user) {
            $this->cache->forgetUser($user->id);
        }
    }
}