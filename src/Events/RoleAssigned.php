<?php

namespace RbacSuite\OmniAccess\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RbacSuite\OmniAccess\Models\Role;
use Illuminate\Contracts\Auth\Authenticatable;

class RoleAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Authenticatable $user,
        public Role $role
    ) {}
}
