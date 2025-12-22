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
        Schema::create('clauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_id')->constrained('standards')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('clauses')->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->boolean('note')->default(false);
            $table->boolean('is_child')->default(false);
            $table->enum('numbering_type', ['numerical','alphabetical','roman','dot'])->default('numerical');
            $table->string('numbering_value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clauses');
    }
};
