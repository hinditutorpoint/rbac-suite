<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('omni-access.table_names.roles', 'roles');
        $primaryKeyType = config('omni-access.primary_key_type', 'bigint');
        
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($primaryKeyType) {
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
                $table->text('description')->nullable();
                $table->string('guard_name')->default('web');
                $table->string('color')->nullable();
                $table->string('icon')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('slug');
                $table->index('is_default');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('omni-access.table_names.roles', 'roles'));
    }
};