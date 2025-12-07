<?php

namespace RbacSuite\OmniAccess;

use RbacSuite\OmniAccess\Models\Group;
use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Models\Role;

class OmniAccessManager
{
    public function createRole(array $attributes): Role
    {
        return Role::create($attributes);
    }

    public function createPermission(array $attributes): Permission
    {
        return Permission::create($attributes);
    }

    public function createGroup(array $attributes): Group
    {
        return Group::create($attributes);
    }

    public function findRole($identifier): ?Role
    {
        if (is_numeric($identifier)) {
            return Role::find($identifier);
        }

        return Role::where('slug', $identifier)->orWhere('name', $identifier)->first();
    }

    public function findPermission($identifier): ?Permission
    {
        if (is_numeric($identifier)) {
            return Permission::find($identifier);
        }

        return Permission::where('slug', $identifier)->orWhere('name', $identifier)->first();
    }

    public function findGroup($identifier): ?Group
    {
        if (is_numeric($identifier)) {
            return Group::find($identifier);
        }

        return Group::where('slug', $identifier)->orWhere('name', $identifier)->first();
    }

    public function getAllRoles()
    {
        return Role::all();
    }

    public function getAllPermissions()
    {
        return Permission::all();
    }

    public function getAllGroups()
    {
        return Group::with('permissions')->orderBy('sort_order')->get();
    }

    public function clearCache(): void
    {
        if (config('omni-access.cache.enabled')) {
            cache()->flush();
        }
    }
}
