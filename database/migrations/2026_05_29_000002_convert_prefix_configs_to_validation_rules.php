<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prefix_configs', function (Blueprint $table) {
            if (Schema::hasColumn('prefix_configs', 'prefix')) {
                $table->dropColumn('prefix');
            }

            if (Schema::hasColumn('prefix_configs', 'prefix_case')) {
                $table->dropColumn('prefix_case');
            }

            if (Schema::hasColumn('prefix_configs', 'name_case')) {
                $table->dropColumn('name_case');
            }

            if (Schema::hasColumn('prefix_configs', 'name_min_length')) {
                $table->dropColumn('name_min_length');
            }

            if (Schema::hasColumn('prefix_configs', 'name_max_length')) {
                $table->dropColumn('name_max_length');
            }

            if (Schema::hasColumn('prefix_configs', 'allow_numbers_in_name')) {
                $table->dropColumn('allow_numbers_in_name');
            }

            if (Schema::hasColumn('prefix_configs', 'allow_special_characters')) {
                $table->dropColumn('allow_special_characters');
            }

            if (Schema::hasColumn('prefix_configs', 'serial_digits')) {
                $table->dropColumn('serial_digits');
            }

            if (Schema::hasColumn('prefix_configs', 'next_number')) {
                $table->dropColumn('next_number');
            }
        });

        Schema::table('prefix_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('prefix_configs', 'segment_count')) {
                $table->unsignedSmallInteger('segment_count')->default(1)->after('separator');
            }

            if (!Schema::hasColumn('prefix_configs', 'value_min_length')) {
                $table->unsignedSmallInteger('value_min_length')->default(1)->after('segment_count');
            }

            if (!Schema::hasColumn('prefix_configs', 'value_max_length')) {
                $table->unsignedSmallInteger('value_max_length')->default(255)->after('value_min_length');
            }

            if (!Schema::hasColumn('prefix_configs', 'characters_min_length')) {
                $table->unsignedSmallInteger('characters_min_length')->default(1)->after('value_max_length');
            }

            if (!Schema::hasColumn('prefix_configs', 'characters_max_length')) {
                $table->unsignedSmallInteger('characters_max_length')->default(255)->after('characters_min_length');
            }

            if (!Schema::hasColumn('prefix_configs', 'segments')) {
                $table->jsonb('segments')->nullable()->after('characters_max_length');
            }
        });

        DB::table('prefix_configs')
            ->whereNull('segments')
            ->update([
                'segments' => DB::raw("'[{\"type\":\"any\",\"case\":\"any\",\"min_length\":1,\"max_length\":255,\"starts_with\":\"any\"}]'::jsonb"),
            ]);

        DB::statement('ALTER TABLE prefix_configs ALTER COLUMN segments SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('prefix_configs', function (Blueprint $table) {
            if (Schema::hasColumn('prefix_configs', 'segment_count')) {
                $table->dropColumn('segment_count');
            }

            if (Schema::hasColumn('prefix_configs', 'value_min_length')) {
                $table->dropColumn('value_min_length');
            }

            if (Schema::hasColumn('prefix_configs', 'value_max_length')) {
                $table->dropColumn('value_max_length');
            }

            if (Schema::hasColumn('prefix_configs', 'characters_min_length')) {
                $table->dropColumn('characters_min_length');
            }

            if (Schema::hasColumn('prefix_configs', 'characters_max_length')) {
                $table->dropColumn('characters_max_length');
            }

            if (Schema::hasColumn('prefix_configs', 'segments')) {
                $table->dropColumn('segments');
            }
        });

        Schema::table('prefix_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('prefix_configs', 'prefix')) {
                $table->string('prefix')->nullable()->after('master_name');
            }

            if (!Schema::hasColumn('prefix_configs', 'prefix_case')) {
                $table->string('prefix_case')->default('upper')->after('separator');
            }

            if (!Schema::hasColumn('prefix_configs', 'name_case')) {
                $table->string('name_case')->default('any')->after('prefix_case');
            }

            if (!Schema::hasColumn('prefix_configs', 'name_min_length')) {
                $table->unsignedSmallInteger('name_min_length')->default(1)->after('name_case');
            }

            if (!Schema::hasColumn('prefix_configs', 'name_max_length')) {
                $table->unsignedSmallInteger('name_max_length')->default(255)->after('name_min_length');
            }

            if (!Schema::hasColumn('prefix_configs', 'allow_numbers_in_name')) {
                $table->boolean('allow_numbers_in_name')->default(true)->after('name_max_length');
            }

            if (!Schema::hasColumn('prefix_configs', 'allow_special_characters')) {
                $table->boolean('allow_special_characters')->default(false)->after('allow_numbers_in_name');
            }

            if (!Schema::hasColumn('prefix_configs', 'serial_digits')) {
                $table->unsignedSmallInteger('serial_digits')->default(4)->after('allow_special_characters');
            }

            if (!Schema::hasColumn('prefix_configs', 'next_number')) {
                $table->unsignedBigInteger('next_number')->default(1)->after('serial_digits');
            }
        });
    }
};
