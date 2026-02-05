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
        Schema::create('clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('clusters')->nullOnDelete()->comment('master clusters id if this is a lab override');

            $table->string('name');
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
            CREATE UNIQUE INDEX clusters_unique_super_admin_name
            ON clusters (name)
            WHERE owner_type = 'super_admin'
              AND owner_id IS NULL
              AND deleted_at IS NULL
        ");

        // ✅ Super admin: unique identifier
        DB::statement("
            CREATE UNIQUE INDEX clusters_unique_super_admin_identifier
            ON clusters (identifier)
            WHERE owner_type = 'super_admin'
              AND owner_id IS NULL
              AND deleted_at IS NULL
        ");

        // ✅ Lab-wise unique name
        DB::statement("
            CREATE UNIQUE INDEX clusters_unique_lab_name
            ON clusters (name, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");

        // ✅ Lab-wise unique identifier
        DB::statement("
            CREATE UNIQUE INDEX clusters_unique_lab_identifier
            ON clusters (identifier, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clusters_unique_super_admin_name');
        DB::statement('DROP INDEX IF EXISTS clusters_unique_super_admin_identifier');
        DB::statement('DROP INDEX IF EXISTS clusters_unique_lab_name');
        DB::statement('DROP INDEX IF EXISTS clusters_unique_lab_identifier');

        Schema::dropIfExists('clusters');
    }
};
