<?php

namespace RbacSuite\OmniAccess\Commands;

use Illuminate\Console\Command;
use RbacSuite\OmniAccess\Models\Role;
use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Models\Group;

class CreateRoleCommand extends Command
{
    protected $signature = 'omni-access:create-role 
                            {name : The role name}
                            {--slug= : The role slug}
                            {--guard=web : The guard name}
                            {--description= : The role description}';

    protected $description = 'Create a new role';

    public function handle(): int
    {
        $role = Role::create([
            'name' => $this->argument('name'),
            'slug' => $this->option('slug'),
            'guard_name' => $this->option('guard'),
            'description' => $this->option('description'),
        ]);

        $this->info("Role '{$role->name}' created successfully with slug '{$role->slug}'");
        
        return self::SUCCESS;
    }
}

class CreatePermissionCommand extends Command
{
    protected $signature = 'omni-access:create-permission 
                            {name : The permission name}
                            {--slug= : The permission slug}
                            {--guard=web : The guard name}
                            {--group= : The group slug}
                            {--description= : The permission description}';

    protected $description = 'Create a new permission';

    public function handle(): int
    {
        $groupId = null;
        if ($groupSlug = $this->option('group')) {
            $group = Group::where('slug', $groupSlug)->first();
            $groupId = $group?->id;
        }

        $permission = Permission::create([
            'name' => $this->argument('name'),
            'slug' => $this->option('slug'),
            'guard_name' => $this->option('guard'),
            'group_id' => $groupId,
            'description' => $this->option('description'),
        ]);

        $this->info("Permission '{$permission->name}' created with slug '{$permission->slug}'");
        
        return self::SUCCESS;
    }
}

class AssignRoleCommand extends Command
{
    protected $signature = 'omni-access:assign-role 
                            {user : The user ID or email}
                            {role : The role slug or name}';

    protected $description = 'Assign a role to a user';

    public function handle(): int
    {
        $userModel = config('omni-access.user_model');
        $identifier = $this->argument('user');
        
        $user = is_numeric($identifier) 
            ? $userModel::find($identifier)
            : $userModel::where('email', $identifier)->first();

        if (!$user) {
            $this->error("User not found: {$identifier}");
            return self::FAILURE;
        }

        $user->assignRole($this->argument('role'));
        
        $this->info("Role '{$this->argument('role')}' assigned to user '{$user->email}'");
        
        return self::SUCCESS;
    }
}

class ListRolesCommand extends Command
{
    protected $signature = 'omni-access:list-roles';
    protected $description = 'List all roles';

    public function handle(): int
    {
        $roles = Role::with('permissions')->get();

        $this->table(
            ['ID', 'Name', 'Slug', 'Guard', 'Permissions Count'],
            $roles->map(fn ($role) => [
                $role->id,
                $role->name,
                $role->slug,
                $role->guard_name,
                $role->permissions->count(),
            ])
        );

        return self::SUCCESS;
    }
}

class ListPermissionsCommand extends Command
{
    protected $signature = 'omni-access:list-permissions {--group= : Filter by group slug}';
    protected $description = 'List all permissions';

    public function handle(): int
    {
        $query = Permission::with('group');
        
        if ($groupSlug = $this->option('group')) {
            $query->whereHas('group', fn ($q) => $q->where('slug', $groupSlug));
        }

        $permissions = $query->get();

        $this->table(
            ['ID', 'Name', 'Slug', 'Group', 'Guard'],
            $permissions->map(fn ($p) => [
                $p->id,
                $p->name,
                $p->slug,
                $p->group?->name ?? '-',
                $p->guard_name,
            ])
        );

        return self::SUCCESS;
    }
}

class CacheResetCommand extends Command
{
    protected $signature = 'omni-access:cache-reset';
    protected $description = 'Reset all OmniAccess cache';

    public function handle(): int
    {
        app(\RbacSuite\OmniAccess\Services\CacheService::class)->flush();
        
        $this->info('OmniAccess cache cleared successfully!');
        
        return self::SUCCESS;
    }
}

class ShowUserRolesCommand extends Command
{
    protected $signature = 'omni-access:show-user {user : User ID or email}';
    protected $description = 'Show user roles and permissions';

    public function handle(): int
    {
        $userModel = config('omni-access.user_model');
        $identifier = $this->argument('user');
        
        $user = is_numeric($identifier) 
            ? $userModel::find($identifier)
            : $userModel::where('email', $identifier)->first();

        if (!$user) {
            $this->error("User not found: {$identifier}");
            return self::FAILURE;
        }

        $this->info("User: {$user->name} ({$user->email})");
        $this->newLine();
        
        $this->info('Roles:');
        $this->table(
            ['Name', 'Slug'],
            $user->roles->map(fn ($r) => [$r->name, $r->slug])
        );
        
        $this->newLine();
        $this->info('Permissions:');
        
        $grouped = $user->getGroupedPermissions();
        foreach ($grouped as $group => $permissions) {
            $this->line("  <fg=yellow>{$group}</>");
            foreach ($permissions as $permission) {
                $this->line("    - {$permission->slug}");
            }
        }

        return self::SUCCESS;
    }
}