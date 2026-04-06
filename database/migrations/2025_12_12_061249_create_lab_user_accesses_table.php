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
        Schema::create('lab_user_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_user_id')->constrained('lab_users')->onDelete('cascade'); 
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->foreignId('lab_location_department_id')->nullable()->constrained('lab_location_departments')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade'); 
            $table->enum('type', ['temp', 'permanent'])->default('temp');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->unique([
                'lab_user_id',
                'role_id',
                'location_id',
                'lab_location_department_id'
            ], 'unique_user_role_scope');

            $table->index(['lab_user_id', 'status']);
            $table->index(['location_id']);
            $table->index(['lab_location_department_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_user_accesses');
    }
};
