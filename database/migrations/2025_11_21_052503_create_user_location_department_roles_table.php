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
        Schema::create('user_location_department_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('location_id')->constrained()->cascadeOnDelete();
                $table->foreignId('department_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->enum('status', ['active','deactive'])->default('active');
                $table->enum('position_type',['temporary','permanent'])->default('permanent');
                $table->dateTime('start_at')->nullable();
                $table->dateTime('end_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->unique(['user_id','location_id','department_id','role_id'], 'uldr_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_location_department_roles');
    }
};
