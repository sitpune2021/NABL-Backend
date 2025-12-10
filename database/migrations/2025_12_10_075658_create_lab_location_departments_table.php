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
        Schema::create('lab_location_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_location_id')->constrained('lab_locations')->onDelete('cascade'); 
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_location_departments');
    }
};
