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
        Schema::create('user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();

            $table->timestamps();
        });

         DB::statement("
            CREATE UNIQUE INDEX ua_unique_no_location
            ON user_assignments (user_id, lab_id, role_id)
            WHERE location_id IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX ua_unique_with_location
            ON user_assignments (user_id, lab_id, location_id, role_id)
            WHERE location_id IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_assignments');
    }
};
