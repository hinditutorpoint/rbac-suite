<?php

namespace RbacSuite\OmniAccess\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RbacSuite\OmniAccess\Models\Role;
use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Models\Group;

class InstallCommand extends Command
{
    protected $signature = 'omni-access:install 
                            {--seed : Seed default roles and permissions}
                            {--force : Force overwrite existing data}';
    
    protected $description = 'Install OMNI Access RBAC system';

    public function handle(): int
    {
        $this->info('🚀 Installing OMNI Access...');

        // Publish config
        Artisan::call('vendor:publish', [
            '--provider' => 'RbacSuite\OmniAccess\OmniAccessServiceProvider',
            '--tag' => 'omni-access-config'
        ]);
        $this->info('✅ Config published');

        // Publish migrations
        Artisan::call('vendor:publish', [
            '--provider' => 'RbacSuite\OmniAccess\OmniAccessServiceProvider',
            '--tag' => 'omni-access-migrations'
        ]);
        $this->info('✅ Migrations published');

        // Run migrations
        $this->info('Running migrations...');
        Artisan::call('migrate');
        $this->info('✅ Migrations complete');

        // Seed default data
        if ($this->option('seed')) {
            $this->seedDefaultData();
        }

        // Assign super admin
        if (config('omni-access.auto_assign_super_admin')) {
            $this->assignSuperAdmin();
        }

        $this->info('✨ OMNI Access installed successfully!');

        return self::SUCCESS;
    }


    protected function seedDefaultData(): void
    {
        $this->info('Seeding default data...');

        // Create groups
        foreach (config('omni-access.default_groups', []) as $groupData) {
            Group::firstOrCreate(
                ['slug' => $groupData['slug']],
                $groupData
            );
        }

        // Create permissions
        $groups = Group::all()->keyBy('slug');
        foreach (config('omni-access.default_permissions', []) as $permData) {
            $group = $groups->get($permData['group']);
            unset($permData['group']);
            
            if ($group) {
                $permData['group_id'] = $group->id;
            }

            Permission::firstOrCreate(
                ['slug' => $permData['slug']],
                $permData
            );
        }

        // Create roles
        foreach (config('omni-access.default_roles', []) as $roleData) {
            Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }

        $this->info('✅ Default data seeded');
    }

    protected function assignSuperAdmin(): void
    {
        $userModel = config('omni-access.user_model');
        $firstUser = $userModel::first();

        if ($firstUser && method_exists($firstUser, 'assignRole')) {
            $firstUser->assignRole(config('omni-access.super_admin_role'));
            $this->info("✅ Super admin role assigned to {$firstUser->email}");
        }
    }
}