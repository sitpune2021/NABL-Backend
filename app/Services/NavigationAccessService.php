<?php

namespace App\Services;
use App\Models\NavigationItem;

class NavigationAccessService
{
    public function getAccessModules($user): array
    {
        $labIds = $user->roles->pluck('lab_id');
        $isMaster = $labIds->filter()->isEmpty();

        $items = NavigationItem::with(
            $isMaster ? 'childrenForMaster' : 'children'
        )
        ->whereNull('parent_id')
        ->orderBy('order')
        ->get();

        return $this->mapToAccessModules($items, $isMaster);
    }

    protected function mapToAccessModules($items, bool $isMaster): array
    {
        $modules = [];
        $relation = $isMaster ? 'childrenForMaster' : 'children';

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
