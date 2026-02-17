<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\NavigationItem;



class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    
        $this->seedPermissions();

        $this->seedMasterRoles();

        $this->command->info('✅ Master roles seeded with COMMON permission logic.');
    }

    private function seedPermissions(): void
    {
        $modules = $this->getAccessModules();

        foreach ($modules as $module) {
            foreach ($module['accessor'] as $perm) {
                Permission::firstOrCreate([
                    'name' => $module['key'] . '.' . $perm['value'],
                ]);
            }
        }
    }

     private function seedMasterRoles(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'description' => 'Super Admin',  'level' => 1],
        ];

        foreach ($roles as $roleData) {

            $role = Role::firstOrCreate(
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'lab_id' => 0, // MASTER LEVEL
                ],
                [
                    'level' => $roleData['level'],
                ]
            );

            // SAME OLD PERMISSION ASSIGNMENT LOGIC
            match ($roleData['name']) {

                'Super Admin' =>
                    $role->syncPermissions(Permission::all()),
            };
        }
    }


    private function getAccessModules()
    {
        $items = NavigationItem::with('children')
            ->whereNull('parent_id')
            ->forMaster()
            ->orderBy('order')
            ->get();

        return $this->mapModules($items);
    }


    private function mapModules($items)
    {
        $modules = [];

        foreach ($items as $item) {
            if ($item->children->isNotEmpty()) {
                foreach ($item->children as $child) {
                    $modules[] = [
                        'id' => str_replace([$item->key . '.', '.list'], '', $child->key),
                        'key' =>  str_replace('.list', '', $child->key),
                        'name' => $child->title . ' Management',
                        'accessor' => $this->defaultPerms($child->key),
                    ];
                }
            }
        }

        return $modules;
    }


    private function defaultPerms($key)
    {
         $permissions =   config('master_permissions') ;

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
