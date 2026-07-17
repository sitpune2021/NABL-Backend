<?php

namespace Database\Seeders;

use App\Models\PrefixConfig;
use Illuminate\Database\Seeder;

class PrefixConfigSeeder extends Seeder
{
    private const EXCLUDED_MASTER_KEYS = [
        'units',
        'templates',
        'documents',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSegments = [
            [
                'type' => 'any',
                'case' => 'any',
                'min_length' => 1,
                'max_length' => 255,
                'starts_with' => 'any',
            ],
        ];

        $categorySegments = [
            [
                'type' => 'letters',
                'case' => 'upper',
                'min_length' => 1,
                'max_length' => 4,
                'starts_with' => 'letter',
            ],
        ];

        $subCategorySegments = [
            [
                'type' => 'letters',
                'case' => 'upper',
                'min_length' => 1,
                'max_length' => 4,
                'starts_with' => 'letter',
            ],
            [
                'type' => 'letters',
                'case' => 'upper',
                'min_length' => 1,
                'max_length' => 4,
                'starts_with' => 'letter',
            ],
        ];

        $locationSegments = [
            [
                'type' => 'letters',
                'case' => 'upper',
                'min_length' => 1,
                'max_length' => 4,
                'starts_with' => 'letter',
            ],
            [
                'type' => 'letters',
                'case' => 'upper',
                'min_length' => 1,
                'max_length' => 8,
                'starts_with' => 'letter',
            ],
            [
                'type' => 'letters',
                'case' => 'upper',
                'min_length' => 1,
                'max_length' => 4,
                'starts_with' => 'letter',
            ],
        ];

        foreach (PrefixConfig::MASTER_KEYS as $masterKey => $masterName) {
            if (in_array($masterKey, self::EXCLUDED_MASTER_KEYS, true)) {
                continue;
            }

            $segments = match ($masterKey) {
                'categories', 'departments', 'zones', 'instruments' => $categorySegments,
                'sub_categories', 'clusters' => $subCategorySegments,
                'locations' => $locationSegments,
                default => $defaultSegments,
            };

            PrefixConfig::updateOrCreate(
                ['master_key' => $masterKey],
                [
                    'master_name' => $masterName,
                    'separator' => '-',
                    'segment_count' => count($segments),
                    'value_min_length' => 1,
                    'value_max_length' => 255,
                    'characters_min_length' => 1,
                    'characters_max_length' => 255,
                    'segments' => $segments,
                    'is_active' => true,
                ]
            );
        }
    }
}
