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
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->integer('major_version')->default(1);
            $table->integer('minor_version')->default(0);
            $table->boolean('is_current')->default(false);
            $table->string('full_version'); // e.g., "2.5"
            $table->string('number')->unique(); // code+dept+seq
            $table->string('copy_no')->nullable(); 
            $table->string('quantity_prepared')->nullable(); 
            $table->enum('workflow_state', ['draft','prepared','reviewed','approved','issued','effective','archived'])->nullable()->default('draft');
            $table->enum('version_status', ['active','superseded','obsolete'])->default('active');
            $table->string('review_frequency');
            $table->string('notification_unit');
            $table->string('notification_value');
            $table->timestamp('effective_date');
            $table->json('editor_schema')->nullable(); // HTML/Graph editor JSON
            $table->json('form_fields')->nullable(); // Dynamic form fields JSON
            $table->json('schedule')->nullable(); // e.g., {"frequency": {"type": "Daily", "interval": 1}}
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
