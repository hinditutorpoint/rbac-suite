<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('omni-access.table_names.permission_role', 'permission_role');
        $rolesTable = config('omni-access.table_names.roles', 'roles');
        $permissionsTable = config('omni-access.table_names.permissions', 'permissions');
        $primaryKeyType = config('omni-access.primary_key_type', 'bigint');
        
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($primaryKeyType, $rolesTable, $permissionsTable) {
                // Permission ID
                if ($primaryKeyType === 'uuid') {
                    $table->uuid('permission_id');
                    $table->uuid('role_id');
                } elseif ($primaryKeyType === 'int') {
                    $table->unsignedInteger('permission_id');
                    $table->unsignedInteger('role_id');
                } else {
                    $table->unsignedBigInteger('permission_id');
                    $table->unsignedBigInteger('role_id');
                }
                
                $table->timestamps();
                
                $table->foreign('permission_id')
                    ->references('id')
                    ->on($permissionsTable)
                    ->onDelete('cascade');
                
                $table->foreign('role_id')
                    ->references('id')
                    ->on($rolesTable)
                    ->onDelete('cascade');
                
                $table->primary(['permission_id', 'role_id']);
                $table->index('role_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('omni-access.table_names.permission_role', 'permission_role'));
    }
};