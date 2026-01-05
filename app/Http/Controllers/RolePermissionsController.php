<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\NavigationItem;

class RolePermissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
            $roles = Role::with('users')->get()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'accessRight' => $this->formatPermissions($role->permissions->pluck('name')->toArray()),
                    'users' => $role->users->map(function ($user) {
                        return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                            // add other user fields as needed
                        ];
                    }),
                ];
            });

        return response()->json($roles);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
            'accessRight' => 'required|array',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description ?? null,
        ]);

        // Fetch access modules (like seeder)
        $modules = $this->getAccessModules();

        // Prepare map of module id => full key
        $moduleMap = [];
        foreach ($modules as $module) {
            $moduleMap[$module['id']] = $module['key'];
        }

        // Build permission strings from payload
        $permissions = [];
        foreach ($request->accessRight as $moduleId => $actions) {
            $fullKey = $moduleMap[$moduleId] ?? null;
            if (!$fullKey) continue;

            foreach ($actions as $action) {
                $permissions[] = $fullKey . '.' . $action;
            }
        }

        // Create permissions if not exist
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // Assign permissions to role
        $role->syncPermissions($permissions);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'accessRight' => $request->accessRight,
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $role = Role::with('users')->findOrFail($id);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'accessRight' => $this->formatPermissions($role->permissions->pluck('name')->toArray()),
            'users' => $role->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    // add other user fields as needed
                ];
            }),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'accessRight' => 'required|array',
        ]);
        // Fetch access modules (like seeder)
        $modules = $this->getAccessModules();

        // Prepare map of module id => full key
        $moduleMap = [];
        foreach ($modules as $module) {
            $moduleMap[$module['id']] = $module['key'];
        }

        // Build permission strings from payload
        $permissions = [];
        foreach ($request->accessRight as $moduleId => $actions) {
            $fullKey = $moduleMap[$moduleId] ?? null;
            if (!$fullKey) continue;

            foreach ($actions as $action) {
                $permissions[] = $fullKey . '.' . $action;
            }
        }

        // Create permissions if not exist
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // Sync updated permissions to role
        $role->syncPermissions($permissions);

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'accessRight' => $request->accessRight,
            ],
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    protected function formatPermissions(array $permissions): array
    {
        $accessRight = [];

        foreach ($permissions as $permission) {
            if (substr_count($permission, '.') < 2) continue;
            [$group, $module, $action] = explode('.', $permission, 3);
            $accessRight[$group][$module][] = $action;
        }
        foreach ($accessRight as $group => $modules) {
            foreach ($modules as $module => $actions) {
                $accessRight[$group][$module] = array_values(array_unique($actions));
            }
        }
        return $accessRight;
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
