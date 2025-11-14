<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
            'accessRight' => 'required|array', // e.g. ['category' => ['read', 'write'], ...]
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description ?? null,
        ]);

        $permissions = $this->preparePermissions($request->accessRight);

        $this->createPermissionsIfNotExist($permissions);

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
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
            'accessRight' => 'required|array',
        ]);

        $role->name = $request->name;
        $role->description = $request->description ?? null;
        $role->save();

        $permissions = $this->preparePermissions($request->accessRight);

        $this->createPermissionsIfNotExist($permissions);

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

    // Helper: Convert permissions array (like ['category.read', 'category.write']) 
    // to grouped accessRight array (like ['category' => ['read', 'write']])
    protected function formatPermissions(array $permissions): array
    {
        $accessRight = [];

        foreach ($permissions as $permission) {
            if (strpos($permission, '.') === false) continue;
            [$module, $action] = explode('.', $permission, 2);
            $accessRight[$module][] = $action;
        }

        // Optional: Remove duplicates
        foreach ($accessRight as $module => $actions) {
            $accessRight[$module] = array_values(array_unique($actions));
        }

        return $accessRight;
    }

    // Helper: Flatten accessRight (grouped) into permission strings
    protected function preparePermissions(array $accessRight): array
    {
        $permissions = [];

        foreach ($accessRight as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = $module . '.' . $action;
            }
        }

        return $permissions;
    }

    // Helper: Create permissions if they don't exist
    protected function createPermissionsIfNotExist(array $permissions)
    {
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }
    }
}
