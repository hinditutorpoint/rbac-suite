<?php

namespace RbacSuite\OmniAccess\Observers;

use RbacSuite\OmniAccess\Models\Group;
use RbacSuite\OmniAccess\Services\CacheService;
use Illuminate\Support\Str;

class GroupObserver
{
    protected CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }

    public function creating(Group $group): void
    {
        if (empty($group->slug)) {
            $group->slug = $this->generateUniqueSlug($group->name);
        }
    }

    // Do NOT modify slug on update
    public function updating(Group $group): void
    {
        if ($group->isDirty('slug')) {
            $group->slug = $group->getOriginal('slug');
        }
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

    protected function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 2;

        while (Group::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
