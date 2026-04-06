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
        Schema::create('lab_instrument_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();

            // Optional scope
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();

            // (Optional future support)
            $table->foreignId('lab_location_department_id')->nullable()->constrained('lab_location_departments')->cascadeOnDelete();

            // Status & tracking
            $table->boolean('is_active')->default(true);

            $table->unique([
                'instrument_id',
                'lab_id',
                'location_id',
                'lab_location_department_id'
            ], 'unique_instrument_scope');

            $table->index(['lab_id']);
            $table->index(['location_id']);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_instrument_assignments');
    }
};
