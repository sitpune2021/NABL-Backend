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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            // 🔗 Document / Clause
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clause_id')->constrained()->cascadeOnDelete();

            // 🔗 Scope
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // 🔥 Type (optional but recommended)
            $table->enum('scope_type', ['location', 'department', 'user']);

            // 🔥 Tracking
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            // 🔥 Prevent duplicates
            $table->unique([
                'document_id',
                'clause_id',
                'location_id',
                'department_id',
                'user_id'
            ], 'unique_assignment_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
