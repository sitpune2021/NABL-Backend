<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $categories = [
            'Manuals',
            'QSP',
            'SOP',
            'Form Format',
            'Records',
            'Display Posters',
            'Reference Document',
            'Internal Document',
            'External Document',
            'Standard',
        ];

        $usedCategoryIds = [];

        // List of subcategories (same for all categories)
        $subCategoryNames = [
            'General Records',
            'Training Records',
            'Personal Records',
            'AMC Records',
            'CMC Records',
            'Legal Records',
            'Safety Records',
            'Instrument Records',
            'Reference Laboratory Record',
            'Checklists',
            'Calibration Certificates',
            'Reference Laboratory Record',
            'QMS Records',
            'Facility Records',
        ];

        foreach ($categories as $catName) {
            $baseCatId = strtoupper(substr(preg_replace('/\s+/', '', $catName), 0, 3));
            $catId = $baseCatId;
            $counter = 1;

            while (in_array($catId, $usedCategoryIds)) {
                $catId = $baseCatId . $counter;
                $counter++;
            }
            $usedCategoryIds[] = $catId;

            $category = Category::firstOrCreate([
                'name' => $catName,
                'identifier' => $catId,
            ]);

            foreach ($subCategoryNames as $subName) {
                $subIdPart = strtoupper(substr(preg_replace('/\s+/', '', $subName), 0, 4));
                $subIdentifier = $catId . '-' . $subIdPart;

                $category->subCategories()->firstOrCreate([
                    'name' => $subName,
                    'identifier' => $subIdentifier,
                ]);
            }
        }
    }
}
