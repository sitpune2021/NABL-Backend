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
            $table->foreignId('parent_id')->nullable()->constrained('documents')->nullOnDelete()->comment('master documents id if this is a lab override');
            $table->string('name');
            $table->string('number')->nullable(); // code+dept+seq this must be unique
            $table->foreignId('category_id')->constrained(); // reference to categories
            $table->enum('status', ['controlled', 'uncontrolled'])->default('controlled');
            $table->enum('mode', ['create', 'upload'])->default('create');
            $table->enum('owner_type', ['super_admin', 'lab'])->default('super_admin');
            $table->foreignId('owner_id')->nullable()->comment('lab_id when owner_type = lab');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
