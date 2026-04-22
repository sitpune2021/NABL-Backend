<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('instruments')->nullOnDelete()->comment('master instruments id if this is a lab override');
            $table->string('name');
            $table->string('short_name');
            $table->string('manufacturer');
            $table->string('serial_no');
            $table->string('identifier');
            $table->enum('status', ['pending', 'completed'])->default('completed');

            $table->enum('owner_type', ['super_admin', 'lab'])->default('super_admin');
            $table->foreignId('owner_id')->comment('lab_id when owner_type = lab')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * 🔐 POSTGRESQL PARTIAL UNIQUE INDEXES
         */

        // ✅ Super admin: unique name
        DB::statement("
            CREATE UNIQUE INDEX instruments_unique_super_admin_name
            ON instruments (name)
            WHERE owner_type = 'super_admin'
              AND owner_id = 0
              AND deleted_at IS NULL
        ");

            // ✅ Super admin: short name
        DB::statement("
            CREATE UNIQUE INDEX instruments_unique_super_admin_short_name
            ON instruments (short_name)
            WHERE owner_type = 'super_admin'
              AND owner_id = 0
              AND deleted_at IS NULL
        ");

        // ✅ Super admin: unique identifier
        DB::statement("
            CREATE UNIQUE INDEX instruments_unique_super_admin_identifier
            ON instruments (identifier)
            WHERE owner_type = 'super_admin'
              AND owner_id = 0
              AND deleted_at IS NULL
        ");

        // ✅ Lab-wise unique name
        DB::statement("
            CREATE UNIQUE INDEX instruments_unique_lab_name
            ON instruments (name, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");

        // ✅ Super admin: short name
        DB::statement("
            CREATE UNIQUE INDEX instruments_unique_lab_short_name
            ON instruments (short_name, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");

        // ✅ Lab-wise unique identifier
        DB::statement("
            CREATE UNIQUE INDEX instruments_unique_lab_identifier
            ON instruments (identifier, owner_id)
            WHERE owner_type = 'lab'
              AND deleted_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS instruments_unique_super_admin_name');
        DB::statement('DROP INDEX IF EXISTS instruments_unique_super_admin_short_name');
        DB::statement('DROP INDEX IF EXISTS instruments_unique_super_admin_identifier');
        DB::statement('DROP INDEX IF EXISTS instruments_unique_lab_name');
        DB::statement('DROP INDEX IF EXISTS instruments_unique_super_admin_short_name');
        DB::statement('DROP INDEX IF EXISTS instruments_unique_lab_identifier');

        Schema::dropIfExists('instruments');
    }
};
