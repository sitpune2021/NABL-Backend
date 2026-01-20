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
            ['name' => 'Admin',       'description' => 'Admin',        'level' => 2],
            ['name' => 'Manager',     'description' => 'Manager',      'level' => 3],
            ['name' => 'User',        'description' => 'User',      'level' => 4],
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

                'Admin' =>
                    $role->syncPermissions(Permission::all()),

                'Manager' =>
                    $role->syncPermissions(
                        Permission::where(fn ($q) =>
                            $q->where('name', 'like', '%.list')
                              ->orWhere('name', 'like', '%.write')
                        )->get()
                    ),

                'User' =>
                    $role->syncPermissions(
                        Permission::where('name', 'like', '%.list')->get()
                    ),
            };
        }
    }


    private function getAccessModules()
    {
        $items = NavigationItem::with('childrenForMaster')
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
        if ($key === 'masters.document.list') {
            return [
                ['label' => 'Read', 'value' => 'list'],
                ['label' => 'Write', 'value' => 'write'],
                ['label' => 'Data Entry', 'value' => 'data-entry'],
                ['label' => 'Data Review', 'value' => 'data-review'],
                ['label' => 'Delete', 'value' => 'delete'],
            ];
        }

        return [
            ['label' => 'Read', 'value' => 'list'],
            ['label' => 'Write', 'value' => 'write'],
            ['label' => 'Delete', 'value' => 'delete'],
        ];
    }
}
