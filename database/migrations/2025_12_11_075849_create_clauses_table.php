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
            $table->text('note_message')->nullable();
            $table->boolean('note')->default(false);
            $table->boolean('is_child')->default(false);
            $table->enum('numbering_type', ['none', 'numerical','alphabetical-lower', 'alphabetical-upper','roman-lower', 'roman-upper','dot'])->default('none');
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
