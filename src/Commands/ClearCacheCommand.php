<?php

namespace RbacSuite\OmniAccess\Commands;

use Illuminate\Console\Command;
use RbacSuite\OmniAccess\Services\CacheService;

class ClearCacheCommand extends Command
{
    protected $signature = 'omni-access:clear-cache 
                            {--user=* : Clear specific user cache}
                            {--role=* : Clear specific role cache}
                            {--all : Clear all OMNI Access cache}';
    
    protected $description = 'Clear OMNI Access cache';

    public function handle(CacheService $cache): int
    {
        if ($this->option('all')) {
            $cache->flush();
            $this->info('✅ All OMNI Access cache cleared!');
            return self::SUCCESS;
        }

        $cleared = [];

        // Clear specific user cache
        if ($userIds = $this->option('user')) {
            foreach ($userIds as $userId) {
                $cache->forgetUser($userId);
                $cleared[] = "User #{$userId}";
            }
        }

        // Clear specific role cache
        if ($roleIds = $this->option('role')) {
            foreach ($roleIds as $roleId) {
                $cache->forgetRole($roleId);
                $cleared[] = "Role #{$roleId}";
            }
        }

        if (empty($cleared)) {
            $this->warn('No specific cache cleared. Use --all to clear everything.');
            return self::SUCCESS;
        }

        $this->info('✅ Cache cleared for: ' . implode(', ', $cleared));
        return self::SUCCESS;
    }
}