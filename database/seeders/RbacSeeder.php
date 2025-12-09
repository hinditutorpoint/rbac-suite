<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RbacSuite\OmniAccess\Models\Role;
use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Models\Group;
use App\Models\User;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // Create Groups
        $groups = [
            ['name' => 'User Management', 'slug' => 'users', 'sort_order' => 1],
            ['name' => 'Role Management', 'slug' => 'roles', 'sort_order' => 2],
            ['name' => 'Post Management', 'slug' => 'posts', 'sort_order' => 3],
        ];

        foreach ($groups as $group) {
            Group::firstOrCreate(['slug' => $group['slug']], $group);
        }

        // Create Permissions
        $permissions = [
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users'],
            ['name' => 'Edit Users', 'slug' => 'users.edit', 'group' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'users'],
            ['name' => 'View Posts', 'slug' => 'posts.view', 'group' => 'posts'],
                        ['name' => 'Create Posts', 'slug' => 'posts.create', 'group' => 'posts'],
            ['name' => 'Edit Posts', 'slug' => 'posts.edit', 'group' => 'posts'],
            ['name' => 'Delete Posts', 'slug' => 'posts.delete', 'group' => 'posts'],
        ];

        foreach ($permissions as $perm) {
            $group = Group::where('slug', $perm['group'])->first();
            
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                [
                    'name' => $perm['name'],
                    'slug' => $perm['slug'],
                    'group_id' => $group?->id,
                ]
            );
        }

        // Create Roles
        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'description' => 'Full system access',
                'permissions' => [],
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Administrative access',
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
                ],
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can manage posts',
                'permissions' => ['posts.view', 'posts.create', 'posts.edit'],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);
            
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
            
            if (!empty($permissions)) {
                $role->syncPermissions($permissions);
            }
        }

        // Create Super Admin User
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
            ]
        );
        $superAdmin->assignRole('super-admin');

        $this->command->info('RBAC seeded successfully!');
    }
}