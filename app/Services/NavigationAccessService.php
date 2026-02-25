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
        foreach ($items as $item) {
            $this->extractModules($item, $modules, $isMaster, $isGroup);
        }

        return $modules;
    }

    protected function extractModules($item, &$modules, bool $isMaster, bool $isGroup)
    {
        // Only process module menu
        if (str_ends_with($item->key, '.list')) {

            $baseKey = str_replace('.list', '', $item->key);

            $parts = explode('.', $baseKey);
            $group = $parts[0];
            $module = end($parts);

            $moduleData = [
                'id' => $module,
                'key' => $baseKey,
                'name' => $item->title . ' Management',
                'description' => 'Access control for ' . strtolower($item->title),
                'linkedMenuKeys' => [$item->key],
                'accessor' => $this->getDefaultAccessors($item->key, $isMaster),
            ];

            if ($isGroup) {
                $modules[$group][] = $moduleData;
            } else {
                $modules[] = $moduleData;
            }
        }

        // 🔁 Traverse all children recursively
        foreach ($item->children ?? [] as $child) {
            $this->extractModules($child, $modules, $isMaster, $isGroup);
        }
    }

    protected function getDefaultAccessors(string $key, bool $isMaster): array
    {
        $permissions =  !$isMaster ? config('master_permissions') : config('user_permissions');
        
        $baseKey = str_replace('.list', '', $key);

        return collect($permissions)
            ->filter(function ($perm) use ($baseKey) {

                if (!str_starts_with($perm, $baseKey . '.')) {
                    return false;
                }

                // check next segment is valid action
                $remaining = substr($perm, strlen($baseKey) + 1);
                $firstSegment = explode('.', $remaining)[0];

                // allowed action keywords
                $allowed = [
                    'list',
                    'write',
                    'delete',
                    'sync',
                    'action',
                    'workflow-logs',
                    'version',
                    'clause',
                    'location'
                ];

                return in_array($firstSegment, $allowed);

            })
            ->map(function ($perm) use ($baseKey) {

                $action = str_replace($baseKey . '.', '', $perm);

                return [
                    'label' => ucfirst(str_replace(['-', '.'], ' ', $action)),
                    'value' => $action,
                ];
            })
            ->values()
            ->toArray();
    }

}
