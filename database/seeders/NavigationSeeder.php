<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NavigationItem;

class NavigationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $navigation = config('navigation');

        foreach ($navigation as $item) {
            $this->storeNavigationItem($item);
        }

        $this->command->info('✅ Navigation seeded successfully');
    }

    protected function storeNavigationItem(array $data, $parentId = null)
    {
        // Extract meta description safely
        $description = $data['meta']['description']['label'] ?? null;
        $descriptionKey = $data['meta']['description']['translateKey'] ?? null;

        $itemData = [
            'key' => $data['key'],
            'path' => $data['path'] ?? null,
            'title' => $data['title'],
            'translate_key' => $data['translateKey'] ?? null,
            'icon' => $data['icon'] ?? null,
            'type' => $data['type'] ?? 'item',
            'for' => $data['for'] ?? 'both',
            'is_external_link' => $data['is_external_link'] ?? false,
            'authority' => $data['authority'] ?? [],
            'description' => $description,
            'description_key' => $descriptionKey,
            'parent_id' => $parentId,
            'layout' => $data['layout'] ?? null,
            'show_column_title' => $data['show_column_title'] ?? false,
            'columns' => $data['columns'] ?? null,
            'order' => $data['order'] ?? 0,
        ];

        $item = NavigationItem::updateOrCreate(
            ['key' => $data['key']],
            $itemData
        );

        // Recursively store children if exist (e.g. 'subMenu')
        if (!empty($data['subMenu']) && is_array($data['subMenu'])) {
            foreach ($data['subMenu'] as $child) {
                $this->storeNavigationItem($child, $item->id);
            }
        }

        return $item;
    }
}
