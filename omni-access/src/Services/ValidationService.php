<?php

namespace RbacSuite\OmniAccess\Services;

use Illuminate\Support\Facades\Validator;
use RbacSuite\OmniAccess\Exceptions\ValidationException;

class ValidationService
{
    public function validateRoleName(string $name): bool
    {
        $regex = config('omni-access.validation.role_name_regex');
        $maxLength = config('omni-access.validation.max_role_name_length');

        $validator = Validator::make(
            ['name' => $name],
            [
                'name' => [
                    'required',
                    'string',
                    "max:{$maxLength}",
                    "regex:{$regex}"
                ]
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException('Invalid role name: ' . $validator->errors()->first());
        }

        return true;
    }

    public function validatePermissionSlug(string $slug): bool
    {
        $regex = config('omni-access.validation.permission_slug_regex');
        $maxLength = config('omni-access.validation.max_permission_name_length');

        $validator = Validator::make(
            ['slug' => $slug],
            [
                'slug' => [
                    'required',
                    'string',
                    "max:{$maxLength}",
                    "regex:{$regex}"
                ]
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException('Invalid permission slug: ' . $validator->errors()->first());
        }

        return true;
    }

    public function validateUserModel($user): bool
    {
        if (!method_exists($user, 'hasRole')) {
            throw new ValidationException('User model must use HasRoles trait');
        }

        return true;
    }
}