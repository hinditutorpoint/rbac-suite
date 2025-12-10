<?php

namespace RbacSuite\OmniAccess\Traits;

use Illuminate\Support\Str;

trait HasPrimaryKeyType
{
    protected static function bootHasPrimaryKeyType(): void
    {
        static::creating(function ($model) {
            $primaryKeyType = config('omni-access.primary_key_type', 'bigint');

            if ($primaryKeyType === 'uuid' && empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected function initializeHasPrimaryKeyType()
    {
        $primaryKeyType = config('omni-access.primary_key_type', 'bigint');

        if ($primaryKeyType === 'uuid') {
            $this->keyType = 'string';
            $this->incrementing = false;
        } else {
            $this->keyType = 'int';
            $this->incrementing = true;
        }
    }
}
