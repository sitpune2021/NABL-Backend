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
        // ----------------------------
        // model_has_roles
        // ----------------------------
        Schema::table('model_has_roles', function (Blueprint $table) {
            // Drop old PK
            $table->dropPrimary();

            // Add surrogate key
            $table->bigIncrements('id');

            // Make lab_id nullable
            $table->unsignedBigInteger('lab_id')->nullable()->change();

            // Add unique constraint
            $table->unique(
                ['role_id', 'model_id', 'model_type', 'lab_id'],
                'model_has_roles_unique'
            );
        });

        // ----------------------------
        // model_has_permissions
        // ----------------------------
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary();
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lab_id')->nullable()->change();

            $table->unique(
                ['permission_id', 'model_id', 'model_type', 'lab_id'],
                'model_has_permissions_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropUnique('model_has_roles_unique');
            $table->dropColumn('id');

            $table->unsignedBigInteger('lab_id')->nullable(false)->change();

            $table->primary(
                ['role_id', 'model_id', 'model_type', 'lab_id'],
                'model_has_roles_pkey'
            );
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropUnique('model_has_permissions_unique');
            $table->dropColumn('id');

            $table->unsignedBigInteger('lab_id')->nullable(false)->change();

            $table->primary(
                ['permission_id', 'model_id', 'model_type', 'lab_id'],
                'model_has_permissions_pkey'
            );
        });
    }
};
