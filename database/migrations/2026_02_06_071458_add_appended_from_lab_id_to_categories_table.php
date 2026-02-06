<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('appended_from_lab_id')
                ->nullable()
                ->after('owner_id');

            $table
                ->foreign('appended_from_lab_id')
                ->references('id')
                ->on('labs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['appended_from_lab_id']);
            $table->dropColumn('appended_from_lab_id');
        });
    }
};
