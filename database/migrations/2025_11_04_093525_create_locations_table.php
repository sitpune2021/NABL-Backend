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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('clusters')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete()->comment('master locations id if this is a lab override');
            
            $table->string('name');
            $table->string('short_name');
            $table->string('identifier'); 

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
            CREATE UNIQUE INDEX locations_unique_super_admin_name
            ON locations (name)
            WHERE owner_type = 'super_admin'
              AND owner_id IS NULL
              AND deleted_at IS NULL
        ");

        // ✅ Super admin: short name
        DB::statement("
            CREATE UNIQUE INDEX locations_unique_super_admin_short_name
            ON locations (short_name)
            WHERE owner_type = 'super_admin'
              AND owner_id IS NULL
              AND deleted_at IS NULL
        ");

        // ✅ Super admin: unique identifier
        DB::statement("
            CREATE UNIQUE INDEX locations_unique_super_admin_identifier
            ON locations (identifier)
            WHERE owner_type = 'super_admin'
              AND owner_id IS NULL
              AND deleted_at IS NULL
        ");

        // ✅ Lab-wise unique name
        DB::statement("
            CREATE UNIQUE INDEX locations_unique_lab_name
            ON locations (name, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");

        // ✅ Super admin: short name
        DB::statement("
            CREATE UNIQUE INDEX locations_unique_lab_short_name
            ON locations (short_name, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");

        // ✅ Lab-wise unique identifier
        DB::statement("
            CREATE UNIQUE INDEX locations_unique_lab_identifier
            ON locations (identifier, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS locations_unique_super_admin_name');
        DB::statement('DROP INDEX IF EXISTS locations_unique_super_admin_short_name');
        DB::statement('DROP INDEX IF EXISTS locations_unique_super_admin_identifier');
        DB::statement('DROP INDEX IF EXISTS locations_unique_lab_name');
        DB::statement('DROP INDEX IF EXISTS locations_unique_lab_short_name');
        DB::statement('DROP INDEX IF EXISTS locations_unique_lab_identifier');

        Schema::dropIfExists('locations');
    }
};
