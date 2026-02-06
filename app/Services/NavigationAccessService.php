<?php

namespace App\Services;
use App\Models\NavigationItem;
use Spatie\Permission\PermissionRegistrar;


class NavigationAccessService
{
    public function getAccessModules($isMaster, $isGroup = true): array
    {
        request()->attributes->set('isLabUser', $isMaster);
        $items = NavigationItem::with('children')
        ->whereNull('parent_id')
        ->orderBy('order')
        ->get();

        return $this->mapToAccessModules($items, $isMaster, $isGroup);
    }

    protected function mapToAccessModules($items, bool $isMaster, bool $isGroup): array
    {
        $modules = [];
        $relation = 'children';
        if ($isGroup) {
            foreach ($items as $item) {
                foreach ($item->{$relation} ?? [] as $child) {
                    [$group, $module] = explode('.', str_replace('.list', '', $child->key));
                    
                    $modules[$group][] = [
                        'id' => $module,
                        'key' => str_replace('.list', '', $child->key),
                        'name' => $child->title . ' Management',
                        'description' => 'Access control for ' . strtolower($child->title),
                        'linkedMenuKeys' => [$child->key],
                        'accessor' => $this->getDefaultAccessors($child->key),
                    ];
                }
            }
        }else{
            foreach ($items as $item) {
                foreach ($item->{$relation} ?? [] as $child) {
                    $modules[] = [
                        'id' => str_replace([$item->key . '.', '.list'], '', $child->key),
                        'key' => str_replace('.list', '', $child->key),
                        'name' => $child->title . ' Management',
                        'description' => 'Access control for ' . strtolower($child->title),
                        'linkedMenuKeys' => [$child->key],
                        'accessor' => $this->getDefaultAccessors($child->key),
                    ];
                }
            }
        }

        

        return $modules;
    }

    protected function getDefaultAccessors(string $key): array
    {
        return $key === 'masters.document.list'
            ? [
                ['label' => 'Read', 'value' => 'list'],
                ['label' => 'Write', 'value' => 'write'],
                ['label' => 'Data Entry', 'value' => 'data-entry'],
                ['label' => 'Data Review', 'value' => 'data-review'],
                ['label' => 'Delete', 'value' => 'delete'],
            ]
            : [
                ['label' => 'Read', 'value' => 'list'],
                ['label' => 'Write', 'value' => 'write'],
                ['label' => 'Delete', 'value' => 'delete'],
            ];
    }
}
