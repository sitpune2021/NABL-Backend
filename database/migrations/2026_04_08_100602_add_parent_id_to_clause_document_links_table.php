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
        Schema::table('clause_document_links', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('clause_document_links')->nullOnDelete()->comment('master category id if this is a lab override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clause_document_links', function (Blueprint $table) {
            //
        });
    }
};
