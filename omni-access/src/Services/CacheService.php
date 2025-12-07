<?php

namespace RbacSuite\OmniAccess\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    protected bool $enabled;
    protected int $ttl;
    protected string $prefix;

    public function __construct()
    {
        $this->enabled = config('omni-access.cache.enabled', true);
        $this->ttl = config('omni-access.cache.expiration_time', 1440);
        $this->prefix = config('omni-access.cache.key_prefix', 'omni_access');
    }

    public function remember(string $key, \Closure $callback, ?int $ttl = null)
    {
        if (!$this->enabled) {
            return $callback();
        }

        $key = $this->prefix . ':' . $key;
        $ttl = $ttl ?? $this->ttl;

        return Cache::remember($key, $ttl, $callback);
    }

    public function forget(string $key): void
    {
        if (!$this->enabled) {
            return;
        }

        Cache::forget($this->prefix . ':' . $key);
    }

    public function flush(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Clear only omni-access cache keys
        $this->forgetPattern($this->prefix . ':*');
    }

    protected function forgetPattern(string $pattern): void
    {
        // Basic implementation - can be overridden in PRO
        $store = Cache::getStore();
        
        if (method_exists($store, 'connection')) {
            $store->connection()->del($store->connection()->keys($pattern));
        }
    }

    // User cache methods
    public function forgetUser(int|string $userId): void
    {
        $this->forget("user.{$userId}.roles");
        $this->forget("user.{$userId}.permissions");
    }

    // Role cache methods
    public function forgetRole(int|string $roleId): void
    {
        $this->forget("role.{$roleId}");
        $this->forget("role.{$roleId}.permissions");
    }

    // Permission cache methods
    public function forgetPermission(int|string $permissionId): void
    {
        $this->forget("permission.{$permissionId}");
    }
}