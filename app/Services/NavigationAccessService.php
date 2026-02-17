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
                        'accessor' => $this->getDefaultAccessors($child->key, $isMaster),
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
                        'accessor' => $this->getDefaultAccessors($child->key, $isMaster),
                    ];
                }
            }
        }

        

        return $modules;
    }

    protected function getDefaultAccessors(string $key, bool $isMaster): array
    {
        $permissions =  !$isMaster ? config('master_permissions') : config('user_permissions');

        // Remove ".list" from key
        $baseKey = str_replace('.list', '', $key);

        // Filter permissions matching this module
        $matchedPermissions = collect($permissions)
            ->filter(fn ($perm) => str_starts_with($perm, $baseKey))
            ->map(function ($perm) use ($baseKey) {
                $action = str_replace($baseKey . '.', '', $perm);

                return [
                    'label' => ucfirst(str_replace('-', ' ', $action)),
                    'value' => $action,
                ];
            })
            ->values()
            ->toArray();

        return $matchedPermissions;
    }

}
