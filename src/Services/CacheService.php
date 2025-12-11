<?php

namespace RbacSuite\OmniAccess\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;

class CacheService
{
    protected bool $enabled;
    protected int $ttl;
    protected string $prefix;
    protected ?string $store;
    protected Repository $cache;

    public function __construct()
    {
        $this->enabled = config('omni-access.cache.enabled', true);
        $this->ttl = config('omni-access.cache.expiration_time', 1440);
        $this->prefix = config('omni-access.cache.key_prefix', 'omni_access');
        $this->store = config('omni-access.cache.store');
        $this->cache = $this->store ? Cache::store($this->store) : Cache::store();
    }

    /**
     * Get prefixed cache key
     */
    protected function getKey(string $key): string
    {
        return $this->prefix . ':' . $key;
    }

    /**
     * Remember a value in cache
     */
    public function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        return $this->cache->remember(
            $this->getKey($key),
            $ttl ?? $this->ttl,
            $callback
        );
    }

    /**
     * Get a value from cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) {
            return $default;
        }

        return $this->cache->get($this->getKey($key), $default);
    }

    /**
     * Put a value in cache
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->cache->put($this->getKey($key), $value, $ttl ?? $this->ttl);
    }

    /**
     * Forget a cache key
     */
    public function forget(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->cache->forget($this->getKey($key));
    }

    /**
     * Flush all omni-access cache
     */
    public function flush(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->forgetByTags();
    }

    /**
     * Forget cache by tags or pattern
     */
    protected function forgetByTags(): void
    {
        $store = $this->cache->getStore();

        // Try tags first (Redis, Memcached)
        if (method_exists($store, 'tags')) {
            try {
                Cache::tags([$this->prefix])->flush();
                return;
            } catch (\Exception $e) {
                // Tags not supported, continue with fallback
            }
        }

        // Fallback: forget known keys
        $this->forgetAllKnownKeys();
    }

    /**
     * Forget all known cache keys (fallback method)
     */
    protected function forgetAllKnownKeys(): void
    {
        // Forget static keys
        $this->forget('roles.all');
        $this->forget('permissions.all');
        $this->forget('groups.all');
    }

    /*
    |--------------------------------------------------------------------------
    | User Cache Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Cache user permissions
     */
    public function cacheUserPermissions(int|string $userId, \Closure $callback): mixed
    {
        return $this->remember("user.{$userId}.permissions", $callback);
    }

    /**
     * Cache user roles
     */
    public function cacheUserRoles(int|string $userId, \Closure $callback): mixed
    {
        return $this->remember("user.{$userId}.roles", $callback);
    }

    /**
     * Forget user's direct permissions cache
     */
    public function forgetUserDirectPermissions(int|string $userId): void
    {
        $this->forget("user.{$userId}.direct_permissions");
    }

    /**
     * Forget user cache
     */
    public function forgetUser(int|string $userId): void
    {
        $this->forget("user.{$userId}.roles");
        $this->forget("user.{$userId}.permissions");
        $this->forget("user.{$userId}.direct_permissions");
    }

    /**
     * Forget user permissions cache
     */
    public function forgetUserPermissions(int|string $userId): void
    {
        $this->forget("user.{$userId}.permissions");
    }

    /**
     * Forget user roles cache
     */
    public function forgetUserRoles(int|string $userId): void
    {
        $this->forget("user.{$userId}.roles");
    }

    /*
    |--------------------------------------------------------------------------
    | Role Cache Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Forget role cache
     */
    public function forgetRole(int|string $roleId): void
    {
        $this->forget("role.{$roleId}");
        $this->forget("role.{$roleId}.permissions");
    }

    /**
     * Forget all roles cache
     */
    public function forgetRoles(): void
    {
        $this->forget('roles.all');
    }

    /**
     * Forget role by slug
     */
    public function forgetRoleBySlug(string $slug): void
    {
        $this->forget("role.slug.{$slug}");
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Cache Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Forget permission cache
     */
    public function forgetPermission(int|string $permissionId): void
    {
        $this->forget("permission.{$permissionId}");
    }

    /**
     * Forget all permissions cache
     */
    public function forgetPermissions(): void
    {
        $this->forget('permissions.all');
    }

    /**
     * Forget permission by slug
     */
    public function forgetPermissionBySlug(string $slug): void
    {
        $this->forget("permission.slug.{$slug}");
    }

    /*
    |--------------------------------------------------------------------------
    | Group Cache Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Forget group cache
     */
    public function forgetGroup(int|string $groupId): void
    {
        $this->forget("group.{$groupId}");
    }

    /**
     * Forget all groups cache
     */
    public function forgetGroups(): void
    {
        $this->forget('groups.all');
    }

    /*
    |--------------------------------------------------------------------------
    | Utility Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if caching is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get cache prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get TTL
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }
}