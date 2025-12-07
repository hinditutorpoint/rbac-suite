<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('omni-access.table_names.role_user', 'role_user');
        $rolesTable = config('omni-access.table_names.roles', 'roles');
        $primaryKeyType = config('omni-access.primary_key_type', 'bigint');
        $userPrimaryKeyType = config('omni-access.user_primary_key_type', 'bigint');
        
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($primaryKeyType, $rolesTable, $userPrimaryKeyType) {
                // Role ID
                if ($primaryKeyType === 'uuid') {
                    $table->uuid('role_id');
                } elseif ($primaryKeyType === 'int') {
                    $table->unsignedInteger('role_id');
                } else {
                    $table->unsignedBigInteger('role_id');
                }
                
                // User ID - based on config
                if ($userPrimaryKeyType === 'uuid') {
                    $table->uuid('user_id');
                } elseif ($userPrimaryKeyType === 'int') {
                    $table->unsignedInteger('user_id');
                } else{
                    $table->unsignedBigInteger('user_id');
                }
                
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('role_id')
                    ->references('id')
                    ->on($rolesTable)
                    ->onDelete('cascade');
                
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
                
                $table->primary(['role_id', 'user_id']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('omni-access.table_names.role_user', 'role_user'));
    }
};