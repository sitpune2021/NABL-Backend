<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::create('sub_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cat_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('sub_categories')
                ->nullOnDelete()
                ->comment('master sub-category id if this is a lab override');

            $table->string('name');
            $table->string('identifier');

            $table->enum('owner_type', ['super_admin', 'lab'])
                ->default('super_admin');

            $table->foreignId('owner_id')
                ->nullable()
                ->comment('lab_id when owner_type = lab');

            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * 🔐 PostgreSQL PARTIAL UNIQUE INDEXES
         */

        // ✅ Super admin uniqueness
        DB::statement("
            CREATE UNIQUE INDEX sub_categories_unique_super_admin
            ON sub_categories (identifier, cat_id)
            WHERE owner_type = 'super_admin'
              AND owner_id IS NULL
              AND deleted_at IS NULL
        ");

        // ✅ Lab-wise uniqueness
        DB::statement("
            CREATE UNIQUE INDEX sub_categories_unique_lab
            ON sub_categories (identifier, cat_id, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sub_categories_unique_super_admin');
        DB::statement('DROP INDEX IF EXISTS sub_categories_unique_lab');

        Schema::dropIfExists('sub_categories');
    }
};
