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
        Schema::create('document_editors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade'); // FK to documents table
            $table->json('data_entry_schedule')->nullable();
            $table->json('frequency')->nullable();
            $table->json('item_configs')->nullable();
            $table->json('selected_items')->nullable();
            $table->string('selected_day')->nullable();
            $table->string('selected_month')->nullable();
            $table->string('type')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->json('document')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_editors');
    }
};
