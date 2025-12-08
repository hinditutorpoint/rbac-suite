<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OMNI Access Configuration
    |--------------------------------------------------------------------------
    */
    'edition' => env('OMNI_ACCESS_EDITION', 'FREE'),

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    | Supported: "uuid", "bigint", "int"
    */
    'primary_key_type' => env('OMNI_ACCESS_PRIMARY_KEY', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'table_names' => [
        'roles' => env('OMNI_ACCESS_ROLES_TABLE', 'roles'),
        'permissions' => env('OMNI_ACCESS_PERMISSIONS_TABLE', 'permissions'),
        'groups' => env('OMNI_ACCESS_GROUPS_TABLE', 'permission_groups'),
        'role_user' => 'role_user',
        'permission_role' => 'permission_role',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    */
    'column_names' => [
        'role_pivot_key' => 'role_id',
        'user_pivot_key' => 'user_id',
        'permission_pivot_key' => 'permission_id',
        'group_pivot_key' => 'group_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => env('OMNI_ACCESS_USER_MODEL', 'App\\Models\\User'),
    'user_primary_key_type' => env('OMNI_ACCESS_USER_PRIMARY_KEY', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Auto Configuration
    |--------------------------------------------------------------------------
    */
    'auto_assign_super_admin' => env('OMNI_ACCESS_AUTO_SUPER_ADMIN', true),
    'super_admin_role' => env('OMNI_ACCESS_SUPER_ADMIN_SLUG', 'super-admin'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration (Basic)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('OMNI_ACCESS_CACHE_ENABLED', true),
        'expiration_time' => env('OMNI_ACCESS_CACHE_TTL', 60 * 24), // 24 hours
        'key_prefix' => env('OMNI_ACCESS_CACHE_PREFIX', 'omni_access'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'role_name_regex' => '/^[a-zA-Z0-9\s\-]+$/',
        'permission_slug_regex' => '/^[a-z0-9\.\-\_]+$/',
        'max_role_name_length' => 50,
        'max_permission_name_length' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Roles
    |--------------------------------------------------------------------------
    */
    'default_roles' => [
        [
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Full system access',
            'is_default' => false,
        ],
        [
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrative access',
            'is_default' => false,
        ],
        [
            'name' => 'User',
            'slug' => 'user',
            'description' => 'Regular user access',
            'is_default' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Permission Groups
    |--------------------------------------------------------------------------
    */
    'default_groups' => [
        [
            'name' => 'User Management',
            'slug' => 'user-management',
            'description' => 'User related permissions',
            'sort_order' => 1,
        ],
        [
            'name' => 'Content Management',
            'slug' => 'content-management',
            'description' => 'Content related permissions',
            'sort_order' => 2,
        ],
        [
            'name' => 'System Settings',
            'slug' => 'system-settings',
            'description' => 'System configuration permissions',
            'sort_order' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Permissions
    |--------------------------------------------------------------------------
    */
    'default_permissions' => [
        // User Management
        ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'user-management'],
        ['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'user-management'],
        ['name' => 'Edit Users', 'slug' => 'users.edit', 'group' => 'user-management'],
        ['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'user-management'],

        // Content Management
        ['name' => 'View Content', 'slug' => 'content.view', 'group' => 'content-management'],
        ['name' => 'Create Content', 'slug' => 'content.create', 'group' => 'content-management'],
        ['name' => 'Edit Content', 'slug' => 'content.edit', 'group' => 'content-management'],
        ['name' => 'Delete Content', 'slug' => 'content.delete', 'group' => 'content-management'],

        // System Settings
        ['name' => 'View Settings', 'slug' => 'settings.view', 'group' => 'system-settings'],
        ['name' => 'Edit Settings', 'slug' => 'settings.edit', 'group' => 'system-settings'],
    ],
];