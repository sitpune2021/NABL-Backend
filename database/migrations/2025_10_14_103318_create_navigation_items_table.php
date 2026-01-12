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
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('path')->nullable();
            $table->string('title');
            $table->string('translate_key');
            $table->string('icon');
            $table->enum('type', ['title', 'collapse', 'item']);
            $table->enum('for', ['lab', 'master', 'both']);
            $table->boolean('is_external_link')->nullable();
            $table->json('authority')->nullable();
            $table->string('description')->nullable();
            $table->string('description_key')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->onDelete('cascade');
            $table->enum('layout', ['default', 'columns', 'tabs'])->nullable();
            $table->boolean('show_column_title')->nullable();
            $table->tinyInteger('columns')->nullable(); // 1 to 5
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
