<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('omni-access.table_names.permission_user', 'permission_user');
        $permissionsTable = config('omni-access.table_names.permissions', 'permissions');
        $primaryKeyType = config('omni-access.primary_key_type', 'bigint');
        $userPrimaryKeyType = config('omni-access.user_primary_key_type', 'bigint');

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($primaryKeyType, $userPrimaryKeyType, $permissionsTable) {
                // Permission ID
                if ($primaryKeyType === 'uuid') {
                    $table->uuid('permission_id');
                } elseif ($primaryKeyType === 'int') {
                    $table->unsignedInteger('permission_id');
                } else {
                    $table->unsignedBigInteger('permission_id');
                }

                // User ID
                if ($userPrimaryKeyType === 'uuid') {
                    $table->uuid('user_id');
                } elseif ($userPrimaryKeyType === 'int') {
                    $table->unsignedInteger('user_id');
                } else {
                    $table->unsignedBigInteger('user_id');
                }

                $table->timestamps();

                // Foreign keys
                $table->foreign('permission_id')
                    ->references('id')
                    ->on($permissionsTable)
                    ->onDelete('cascade');

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->primary(['permission_id', 'user_id']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        $tableName = config('omni-access.table_names.permission_user', 'permission_user');
        Schema::dropIfExists($tableName);
    }
};