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
        Schema::create('document_version_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')->constrained('document_versions')->onDelete('cascade');
            $table->foreignId('template_id')
                  ->constrained('templates')
                  ->onDelete('restrict');
            $table->foreignId('template_version_id')
                  ->constrained('template_versions')
                  ->onDelete('restrict');
            $table->enum('type', ['header', 'footer']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_version_templates');
    }
};
