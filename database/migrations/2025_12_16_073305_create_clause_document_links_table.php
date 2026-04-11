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
        Schema::create('clause_document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_id')->constrained('standards')->nullOnDelete()->comment('master category id if this is a lab override');
            $table->foreignId('clause_id')->constrained('clauses')->nullOnDelete()->comment('master category id if this is a lab override');
            $table->foreignId('document_id')->constrained('documents')->nullOnDelete()->comment('master category id if this is a lab override');
            $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->nullOnDelete()->comment('master category id if this is a lab override');

            $table->enum('owner_type', ['super_admin', 'lab'])->default('super_admin');
            $table->foreignId('owner_id')->nullable()->comment('lab_id when owner_type = lab');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clause_document_links');
    }
};
