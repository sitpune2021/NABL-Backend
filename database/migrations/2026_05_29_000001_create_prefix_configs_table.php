<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prefix_configs', function (Blueprint $table) {
            $table->id();
            $table->string('master_key');
            $table->string('master_name');
            $table->string('separator', 5)->default('-');
            $table->unsignedSmallInteger('segment_count');
            $table->unsignedSmallInteger('value_min_length')->default(1);
            $table->unsignedSmallInteger('value_max_length')->default(255);
            $table->unsignedSmallInteger('characters_min_length')->default(1);
            $table->unsignedSmallInteger('characters_max_length')->default(255);
            $table->jsonb('segments');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("
            CREATE UNIQUE INDEX prefix_configs_unique_master_key
            ON prefix_configs (master_key)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS prefix_configs_unique_master_key');
        Schema::dropIfExists('prefix_configs');
    }
};
