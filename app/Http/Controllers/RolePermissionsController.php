<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\NavigationItem;
use App\Services\NavigationAccessService;
use App\Models\LabUser;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $currentUser = auth()->user();
        $labUser = LabUser::where('user_id', $currentUser->id)->first();
        $lab =  $labUser ? $labUser->lab_id  : 0;
        app(PermissionRegistrar::class)->setPermissionsTeamId($lab);
        $minLevel = $currentUser->roles
            ->where('lab_id', $lab)          // ✅ Current lab roles
            ->min('level');

        $roles = Role::query()
            ->with(['users' => function ($q) {
                $q->select('users.id', 'users.name');
            }])
            ->where('lab_id', $lab)                // ✅ MASTER ONLY
            ->where('level', '>', $minLevel)    // ✅ RBAC hierarchy
            ->orderBy('level')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'accessRight' => $this->formatPermissions($role->permissions->pluck('name')->toArray()),
                    'users' => $role->users->map(function ($user) {
                        return [
                        'id' => $user->id,
                        'name' => $user->name,
                        ];
                    }),
                ];
            });

        return response()->json($roles);
    }

    public function levels()
    {
        $levels = Role::select('level')->distinct()->orderBy('level')->pluck('level');
        return response()->json($levels);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, NavigationAccessService $service)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
            'accessRight' => 'required|array',
        ]);

        $currentUser = auth()->user();
          $labUser = LabUser::where('user_id', $currentUser->id)->first();
        $lab =  $labUser ? $labUser->lab_id  : 0;

        app(PermissionRegistrar::class)->setPermissionsTeamId($lab);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $targetLevel = $request->level;
        $maxLevel = Role::where('lab_id', $lab)->max('level') ?? 0;
        if ($targetLevel > $maxLevel) {
            $insertLevel = $maxLevel + 1;
        } else {
            Role::where('lab_id', $lab)->where('level', '>=', $targetLevel)->increment('level');
            $insertLevel = $targetLevel;
        }

        $user = auth()->user();
        $labUser = LabUser::where('user_id', $user->id)->first();

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description ?? null,
            'level' => $insertLevel,
            'lab_id' => $lab
        ]);

        $modules = $service->getAccessModules($user, false);

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

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
        $currentUser = auth()->user();
        $labUser = LabUser::where('user_id', $currentUser->id)->first();
        $lab = $labUser ? $labUser->lab_id  : 0;

        app(PermissionRegistrar::class)->setPermissionsTeamId($lab);

        $role = Role::where('lab_id', $lab)->with('users')->findOrFail($id);

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
    public function update(Request $request, $id, NavigationAccessService $service)
    {
        $currentUser = auth()->user();
        $labUser = LabUser::where('user_id', $currentUser->id)->first();
        $lab = $labUser ? $labUser->lab_id  : 0;

        app(PermissionRegistrar::class)->setPermissionsTeamId($lab);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('lab_id', $lab)->findOrFail($id);

        $request->validate([
            'accessRight' => 'required|array',
        ]);

        $modules = $service->getAccessModules(auth()->user(), false);

        // Map moduleId => full permission key
        $moduleMap = collect($modules)->pluck('key', 'id')->toArray();
        $permissions = [];
        foreach ($request->accessRight as $moduleId => $actions) {
            if (!isset($moduleMap[$moduleId])) {
                continue;
            }

            foreach ($actions as $action) {
                $permissions[] = $moduleMap[$moduleId] . '.' . $action;
            }
        }

        // ✅ Ensure permissions exist WITH guard_name
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name'       => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // ✅ Sync permissions to role
        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => [
                'id'          => $role->id,
                'name'        => $role->name,
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
}
