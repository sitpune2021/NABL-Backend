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
        Schema::table('labs', function (Blueprint $table) {
            $table->foreignId('standard_id')->nullable()->constrained('standards')->comment('master category id if this is a lab override');
            });

            // Step 2: Backfill existing rows
            DB::table('labs')->update([
                'standard_id' => 1 // or some valid default standard id
            ]);

            // Step 3: Make it NOT NULL
            Schema::table('labs', function (Blueprint $table) {
                $table->foreignId('standard_id')->nullable(false)->change();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labs', function (Blueprint $table) {
            //
        });
    }
};
