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

    public function getIncrementing(): bool
    {
        $primaryKeyType = config('omni-access.primary_key_type', 'bigint');

        return $primaryKeyType !== 'uuid';
    }

    public function getKeyType(): string
    {
        $primaryKeyType = config('omni-access.primary_key_type', 'bigint');

        return $primaryKeyType === 'uuid' ? 'string' : 'int';
    }
}
