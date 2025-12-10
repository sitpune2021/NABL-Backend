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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('labName'); 
            $table->string('location'); 
            $table->json('department');
            $table->integer('header')->nullable();
            $table->integer('footer')->nullable();
            $table->string('amendmentNo')->nullable();
            $table->timestamp('amendmentDate')->nullable();
            $table->string('approvedBy')->nullable();
            $table->string('category')->nullable();
            $table->string('copyNo')->nullable();
            $table->string('documentName');
            $table->string('documentNo')->nullable();
            $table->string('durationValue')->nullable();
            $table->string('durationUnit')->nullable();
            $table->timestamp('effectiveDate')->nullable();
            $table->string('frequency')->nullable();
            $table->timestamp('issueDate')->nullable();
            $table->string('issuedBy')->nullable();
            $table->string('issuedNo')->nullable();
            $table->string('preparedBy')->nullable();
            $table->timestamp('preparedByDate')->nullable();
            $table->integer('quantityPrepared')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
