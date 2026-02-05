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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('units')->nullOnDelete()->comment('master units id if this is a lab override');
            $table->string('name');
            $table->enum('owner_type', ['super_admin', 'lab'])->default('super_admin');
            $table->foreignId('owner_id')->nullable()->comment('lab_id when owner_type = lab');
            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * 🔐 POSTGRESQL PARTIAL UNIQUE INDEXES
         */

        // ✅ Super admin: unique name
        DB::statement("
            CREATE UNIQUE INDEX units_unique_super_admin_name
            ON units (name)
            WHERE owner_type = 'super_admin'
              AND owner_id IS NULL
              AND deleted_at IS NULL
        ");

        // ✅ Lab-wise unique name
        DB::statement("
            CREATE UNIQUE INDEX units_unique_lab_name
            ON units (name, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS units_unique_super_admin_name');
        DB::statement('DROP INDEX IF EXISTS units_unique_lab_name');

        Schema::dropIfExists('units');
    }
};
