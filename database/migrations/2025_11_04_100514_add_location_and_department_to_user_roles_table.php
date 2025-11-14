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
        Schema::table('user_roles', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('role_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('department_id')->nullable()->after('location_id')->constrained('departments')->onDelete('cascade');
            $table->unique(['user_id', 'role_id', 'location_id', 'department_id'], 'user_role_location_department_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            //
        });
    }
};
