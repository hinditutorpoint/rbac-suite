<?php

namespace RbacSuite\OmniAccess\Observers;

use RbacSuite\OmniAccess\Models\Group;
use RbacSuite\OmniAccess\Services\CacheService;

class GroupObserver
{
    protected CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }

    public function created(Group $group): void
    {
        $this->clearCache($group);
    }

    public function updated(Group $group): void
    {
        $this->clearCache($group);
    }

    public function deleted(Group $group): void
    {
        $this->clearCache($group);
    }

    protected function clearCache(Group $group): void
    {
        $this->cache->forgetGroup($group->id);
        $this->cache->forgetPermissions();
    }
}
