<?php

namespace RbacSuite\OmniAccess\Facades;

use Illuminate\Support\Facades\Facade;

class OmniAccess extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'omni-access';
    }
}
