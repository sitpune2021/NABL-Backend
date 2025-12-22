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
        Schema::create('document_version_lab_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')->constrained()->onDelete('cascade');
            $table->enum('apply_type', ['all','new_labs'])->default('all');
            $table->timestamp('apply_date')->nullable();
            $table->boolean('effective_for_this_lab')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_version_lab_assignments');
    }
};
