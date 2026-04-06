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
        Schema::create('clause_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clause_id')->constrained('clauses')->cascadeOnDelete();
            $table->foreignId('standard_id')->constrained('standards')->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->enum('numbering_type', ['numerical','alphabetical','roman','dot'])->default('numerical');
            $table->string('numbering_value')->nullable();
            $table->integer('version_major')->default(1);
            $table->integer('version_minor')->default(0);
            $table->enum('change_type', ['added','modified','deleted']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clause_versions');
    }
};
