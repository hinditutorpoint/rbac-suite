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
    | Slug Settings
    |--------------------------------------------------------------------------
    */
    'slug' => [
        'immutable' => true, // Prevent slug changes after creation
        'auto_generate' => true, // Auto-generate slug from name
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
    | Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'default' => null, // null = use Laravel's default guard
        'available' => ['web', 'api', 'admin'], // Available guards for roles/permissions
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration (Basic)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('OMNI_ACCESS_CACHE_ENABLED', true),
        'expiration_time' => env('OMNI_ACCESS_CACHE_TTL', 60 * 24), // 24 hours
        'key_prefix' => env('OMNI_ACCESS_CACHE_PREFIX', 'omni_access'),
        'store' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Settings
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        /*
        |--------------------------------------------------------------------------
        | Register Middleware Aliases
        |--------------------------------------------------------------------------
        */
        'register' => true,
        'aliases' => [
            'role' => \RbacSuite\OmniAccess\Middleware\RoleMiddleware::class,
            'permission' => \RbacSuite\OmniAccess\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \RbacSuite\OmniAccess\Middleware\RoleOrPermissionMiddleware::class,
        ],

        /*
        |--------------------------------------------------------------------------
        | Unauthorized Response Configuration
        |--------------------------------------------------------------------------
        | 
        | response_type: 'json', 'view', 'redirect', 'abort'
        | 
        | For 'view': renders the specified view
        | For 'redirect': redirects to specified URL with flash message
        | For 'json': returns JSON response
        | For 'abort': aborts with status code
        |
        */
        'unauthorized' => [
            // Response type: json, view, redirect, abort
            'response_type' => 'auto', // 'auto' = json for API, abort for web
            
            // HTTP status codes
            'status_code' => [
                'unauthenticated' => 401,
                'forbidden' => 403,
                'invalid_guard' => 403,
                'missing_trait' => 500,
            ],

            // Messages (supports string or array with guard-specific messages)
            'messages' => [
                'unauthenticated' => 'You must be logged in to access this resource.',
                'forbidden_role' => 'You do not have the required role to access this resource.',
                'forbidden_permission' => 'You do not have the required permission to access this resource.',
                'invalid_guard' => 'Invalid authentication guard specified.',
                'missing_trait' => 'User model must use HasRoles trait.',
            ],

            // View configuration (when response_type is 'view')
            'view' => [
                'name' => 'errors.unauthorized', // View name
                'layout' => null, // Optional layout
                'data' => [], // Additional data to pass to view
            ],

            // Redirect configuration (when response_type is 'redirect')
            'redirect' => [
                'url' => '/login',
                'route' => null, // Use route name instead of URL
                'with_message' => true, // Flash message to session
                'message_key' => 'error', // Session key for message
            ],

            // JSON configuration (when response_type is 'json')
            'json' => [
                'include_required' => false, // Include required roles/permissions in response
                'include_user_roles' => false, // Include user's current roles
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Guard Validation
        |--------------------------------------------------------------------------
        */
        'validate_guard' => true, // Validate if guard exists in available guards
        'strict_guard' => false, // If true, guard must match exactly
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