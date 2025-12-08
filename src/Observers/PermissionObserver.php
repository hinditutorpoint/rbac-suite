<?php

namespace RbacSuite\OmniAccess\Observers;

use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Services\CacheService;
use Illuminate\Support\Str;

class PermissionObserver
{
    protected CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }

    public function creating(Permission $permission): void
    {
        if (empty($permission->slug)) {
            $permission->slug = $this->generateUniqueSlug($permission->name);
        }
    }

    public function updating(Permission $permission): void
    {
        if ($permission->isDirty('slug')) {
            $permission->slug = $permission->getOriginal('slug');
        }
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

    /**
     * Generate a unique slug from a given name.
     *
     * This method will generate a slug by first generating a slug with dots, and then
     * incrementing a counter until a unique slug is found.
     *
     * @param string $name
     * @return string
     */
    protected function generateUniqueSlug(string $name): string
    {
        $slug = $this->generateSlugWithDot($name);
        $original = $slug;
        $counter = 2;

        while (Permission::where('slug', $slug)->exists()) {
            $slug = "{$original}.{$counter}";
            $counter++;
        }

        return $slug;
    }
    
    /**
     * Generate a slug with dots.
     *
     * This method will lowercase the string, remove all special characters except for letters, numbers and spaces,
     * and replace spaces with dots.
     *
     * @param string $name
     * @return string
     */
    protected function generateSlugWithDot(string $name): string
    {
        // Lowercase
        $slug = strtolower($name);

        // Special chars remove (letters, numbers, space only)
        $slug = preg_replace('/[^a-z0-9\s]+/', '', $slug);

        // Spaces → dots
        $slug = preg_replace('/\s+/', '.', $slug);

        return $slug;
    }
}