<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('omni-access.table_names.permissions', 'permissions');
        $groupsTable = config('omni-access.table_names.groups', 'permission_groups');
        $primaryKeyType = config('omni-access.primary_key_type', 'bigint');
        
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($primaryKeyType, $groupsTable) {
                // Primary key
                if ($primaryKeyType === 'uuid') {
                    $table->uuid('id')->primary();
                } elseif ($primaryKeyType === 'int') {
                    $table->increments('id');
                } else {
                    $table->id();
                }

                $table->string('name');
                $table->string('slug')->unique();
                $table->string('guard_name')->default('web');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                
                // Foreign key to groups
                if ($primaryKeyType === 'uuid') {
                    $table->uuid('group_id')->nullable();
                } elseif ($primaryKeyType === 'int') {
                    $table->unsignedInteger('group_id')->nullable();
                } else {
                    $table->unsignedBigInteger('group_id')->nullable();
                }
                
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('slug');
                $table->index('group_id');
                $table->index('is_active');
                
                $table->foreign('group_id')
                    ->references('id')
                    ->on($groupsTable)
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('omni-access.table_names.permissions', 'permissions'));
    }
};