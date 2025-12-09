<?php

namespace RbacSuite\OmniAccess\Traits;

use Illuminate\Support\Facades\Auth;
use RbacSuite\OmniAccess\Exceptions\GuardNotFoundException;

trait HasGuards
{
    /**
     * Get the guard name for this model
     */
    public function getGuardName(): string
    {
        // Check if model has guard property
        if (property_exists($this, 'guard_name') && !empty($this->guard_name)) {
            return $this->guard_name;
        }

        // Check if model has guard attribute
        if (isset($this->attributes['guard_name']) && !empty($this->attributes['guard_name'])) {
            return $this->attributes['guard_name'];
        }

        // Check config default
        $configGuard = config('omni-access.guards.default');
        if ($configGuard) {
            return $configGuard;
        }

        // Fallback to Laravel's default guard
        return $this->getDefaultGuardFromAuth();
    }

    /**
     * Get default guard from Auth
     */
    protected function getDefaultGuardFromAuth(): string
    {
        return Auth::getDefaultDriver() ?? 'web';
    }

    /**
     * Check if guard is available
     */
    public function isGuardAvailable(string $guard): bool
    {
        $availableGuards = config('omni-access.guards.available', ['web', 'api']);
        return in_array($guard, $availableGuards) || array_key_exists($guard, config('auth.guards', []));
    }

    /**
     * Validate guard exists
     */
    public function validateGuard(string $guard): void
    {
        if (!$this->isGuardAvailable($guard)) {
            throw new GuardNotFoundException("Guard [{$guard}] is not available.");
        }
    }

    /**
     * Get all available guards
     */
    public function getAvailableGuards(): array
    {
        $configGuards = config('omni-access.guards.available', []);
        $authGuards = array_keys(config('auth.guards', []));
        
        return array_unique(array_merge($configGuards, $authGuards));
    }

    /**
     * Set guard name
     */
    public function setGuardName(string $guard): self
    {
        $this->guard_name = $guard;
        return $this;
    }
}