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
    
        $modules = $this->getAccessModules();

        foreach ($modules as $module) {
            foreach ($module['accessor'] as $perm) {
                $permissionName = $module['key'] . '.' . $perm['value']; 
                Permission::firstOrCreate(['name' => $permissionName]);
            }
        }

    
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin      = Role::firstOrCreate(['name' => 'Admin']);
        $manager    = Role::firstOrCreate(['name' => 'Manager']);
        $user       = Role::firstOrCreate(['name' => 'User']);

   
        $superAdmin->syncPermissions(Permission::all());

   
        $admin->syncPermissions(Permission::all());

        $managerPermissions = Permission::where(function ($q) {
            $q->where('name', 'like', "%.list")
              ->orWhere('name', 'like', "%.write");
        })->get();
        $manager->syncPermissions($managerPermissions);

        $userPermissions = Permission::where('name', 'like', "%.list")->get();
        $user->syncPermissions($userPermissions);

        echo "Roles & Permissions (Super Admin, Admin, Manager, User) seeded successfully.\n";
    }


    private function getAccessModules()
    {
        $items = NavigationItem::with('children')
            ->whereNull('parent_id')
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
