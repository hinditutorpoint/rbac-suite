<?php

namespace RbacSuite\OmniAccess\Exceptions;

use Exception;

class GuardNotFoundException extends Exception
{
    protected string $guard;

    public function __construct(string $message = '', string $guard = '')
    {
        parent::__construct($message);
        $this->guard = $guard;
    }

    public function getGuard(): string
    {
        return $this->guard;
    }
}