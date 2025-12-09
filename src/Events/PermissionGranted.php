<?php

namespace RbacSuite\OmniAccess\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RbacSuite\OmniAccess\Models\Permission;
use RbacSuite\OmniAccess\Models\Role;

class PermissionGranted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Role $role,
        public Permission $permission
    ) {}
}
