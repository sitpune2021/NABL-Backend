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
        Schema::create('document_version_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')->constrained('document_versions')->onDelete('cascade');
            $table->enum('amendment_type', ['major','minor']);
            $table->integer('amendment_number');
            $table->text('amendment_reason')->nullable();
            $table->foreignId('amended_by')->nullable()->constrained('users');
            $table->timestamp('amendment_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_version_amendments');
    }
};
