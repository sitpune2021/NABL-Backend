<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_custom_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_location_department_role_id')
                    ->constrained('user_location_department_roles')
                    ->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                $table->boolean('is_allowed')->default(true);
                $table->softDeletes();
                $table->timestamps();
                $table->unique(['user_location_department_role_id', 'permission_id'], 'user_permission_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_custom_permissions');
    }
};
