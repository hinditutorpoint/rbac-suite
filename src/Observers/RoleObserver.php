<?php

namespace RbacSuite\OmniAccess\Observers;

use RbacSuite\OmniAccess\Models\Role;
use Illuminate\Support\Facades\Auth;
use RbacSuite\OmniAccess\Services\CacheService;
use Illuminate\Support\Str;

class RoleObserver
{
    protected CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }

    public function creating(Role $role): void
    {
        if (empty($role->slug)) {
            $role->slug = $this->generateUniqueSlug($role->name);
        }
        if (empty($role->guard_name)) {
            $role->guard_name = Auth::getDefaultDriver() ?? 'web';
        }
    }

    public function updating(Role $role): void
    {
        if ($role->isDirty('slug')) {
            $role->slug = $role->getOriginal('slug');
        }
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

    public function restored(Role $role): void
    {
        $this->clearCache($role);
    }

    protected function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }

}