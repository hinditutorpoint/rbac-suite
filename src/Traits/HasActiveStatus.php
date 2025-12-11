<?php

namespace RbacSuite\OmniAccess\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasActiveStatus
{
    /**
     * Boot the trait
     */
    public static function bootHasActiveStatus(): void
    {
        // Apply global scope if configured
        if (config('omni-access.status.apply_global_scope', true)) {
            static::addGlobalScope('active', function (Builder $builder) {
                if (config('omni-access.status.filter_inactive', true)) {
                    $builder->where('is_active', true);
                }
            });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to get only active records
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive records
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to get all records regardless of status
     */
    public function scopeWithInactive(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active');
    }

    /**
     * Scope to get only inactive records (without global scope)
     */
    public function scopeOnlyInactive(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active')->where('is_active', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Status Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if the record is active
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Check if the record is inactive
     */
    public function isInactive(): bool
    {
        return !$this->is_active;
    }

    /**
     * Activate the record
     */
    public function activate(): bool
    {
        $this->is_active = true;
        $result = $this->save();
        
        $this->clearStatusCache();
        
        return $result;
    }

    /**
     * Deactivate the record
     */
    public function deactivate(): bool
    {
        $this->is_active = false;
        $result = $this->save();
        
        $this->clearStatusCache();
        
        return $result;
    }

    /**
     * Toggle the active status
     */
    public function toggleStatus(): bool
    {
        $this->is_active = !$this->is_active;
        $result = $this->save();
        
        $this->clearStatusCache();
        
        return $result;
    }

    /**
     * Set the active status
     */
    public function setStatus(bool $active): bool
    {
        $this->is_active = $active;
        $result = $this->save();
        
        $this->clearStatusCache();
        
        return $result;
    }

    /**
     * Clear cache after status change (to be implemented in models)
     */
    protected function clearStatusCache(): void
    {
        // Override in specific models
        
    }
}