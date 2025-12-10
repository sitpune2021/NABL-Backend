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
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->onDelete('cascade');
            $table->unsignedInteger('major')->default(1);
            $table->unsignedInteger('minor')->default(0);
            $table->longText('css')->nullable();
            $table->longText('html')->nullable();
            $table->jsonb('json_data')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('change_type',10)->default('minor');
            $table->string('message')->nullable();
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['template_id','major','minor']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
