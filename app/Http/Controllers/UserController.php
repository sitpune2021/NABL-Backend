<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\StoreUserRequest;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use App\Models\{Lab, Location, LabLocationDepartment, LabUser, Contact, User, LabUserAccess};
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $currentUser = auth()->user();
            $currentRole = $currentUser->roles->first();
            $currentRoleLevel = $currentRole?->level;
            $ctx = $this->labContext($request);

            $query = User::with([
                'labUsers.accesses.role',
                'labUsers.accesses.location',
                'labUsers.accesses.department.department',
            ])->where('id', '!=', $currentUser->id);


            if ($ctx['lab_id'] != 0) {
                // Filter users by the same lab
                $query->whereIn('id', function ($q) use ($ctx) {
                    $q->select('user_id')
                    ->from('lab_users')
                    ->where('lab_id', $ctx['lab_id']);
                });
            } else {
                $query->whereNotIn('id', function ($q) {
                    $q->select('user_id')
                    ->from('lab_users');
                });
            }

            if ($currentRoleLevel !== null) {
                $query->whereHas('roles', function ($q) use ($currentRoleLevel) {
                    $q->where('level', '>=', $currentRoleLevel);
                });
            }

            // Search
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));

                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(username) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
                });
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id');
            $sortOrder = $request->input('sort.order', 'asc');
            $allowedSortColumns = ['id', 'name', 'username', 'email', 'created_at', 'updated_at'];
            $allowedSortOrder = ['asc', 'desc'];

            if (in_array($sortKey, $allowedSortColumns) && in_array(strtolower($sortOrder), $allowedSortOrder)) {
                $query->orderBy($sortKey, $sortOrder);
            }

            // Pagination
            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize = (int) $request->input('pageSize', 10);

            $users = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            // Transform to location → department → role structure
            $data = $users->map(function ($user) {
                $accesses = $user->labUsers->flatMap->accesses;

                $locations = $accesses->groupBy('location_id')->map(function ($locationAccesses) {

                    $location = optional($locationAccesses->first()->location);

                    // ✅ 1. LOCATION LEVEL ROLES (department_id = null)
                    $locationRoles = $locationAccesses
                        ->whereNull('lab_location_department_id')
                        ->map(function ($access) {
                            return [
                                'id' => $access->role->id,
                                'name' => $access->role->name,
                            ];
                        })
                        ->values();

                    // ✅ 2. DEPARTMENT LEVEL ROLES
                    $departments = $locationAccesses
                        ->whereNotNull('lab_location_department_id')
                        ->groupBy('lab_location_department_id')
                        ->map(function ($deptAccesses) {

                            $department = optional($deptAccesses->first()->department);

                            $roles = $deptAccesses->map(function ($access) {
                                return [
                                    'id' => $access->role->id,
                                    'name' => $access->role->name,
                                ];
                            })->values();

                            return [
                                'id' => $department?->department?->id,
                                'name' => $department?->department?->name,
                                'roles' => $roles
                            ];
                        })
                        ->values();

                    return [
                        'id' => $location?->id,
                        'name' => $location?->name,
                        'roles' => $locationRoles,
                        'departments' => $departments
                    ];
                })->values();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'locations' => $locations
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $data,
                'total' => $users->total()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Store a newly created resource in storage.
     */

    public function store(StoreUserRequest $request)
    {
        DB::beginTransaction();
        try {
            $authUser = auth()->user();
            // 1️⃣ Create User
            $user = User::create([
                'name'      => $request->name,
                'username'  => $request->username,
                'email'     => $request->email,
                'dial_code' => $request->dialCode,
                'phone'     => $request->phone,
                'address'   => $request->address,
                'signature' => $request->signature,
                'password'  => Hash::make('password123'), // default
                'is_super_admin' => false,
            ]);


            $ctx = $this->labContext($request);

            app(PermissionRegistrar::class)->setPermissionsTeamId($ctx['owner_id']); // MASTER
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if($ctx['lab_id'] == 0) {
                $Role = Role::where('id', $request->role)
                                ->where('lab_id', $ctx['lab_id'])
                                ->firstOrFail();

                $user->assignRole($Role);
                $permissions = $Role->permissions->pluck('name')->toArray();
                $user->syncPermissions($permissions);
            }

            if ($ctx['lab_id'] != 0) {
              $labuser =   LabUser::create([
                    'user_id' => $user->id,
                    'lab_id'  => $ctx['lab_id'],
                ]);
            }

            // 2️⃣ Assign roles
            foreach ($request->userRoles as $roleBlock) {
                foreach ($roleBlock['department'] as $deptBlock) {
                    foreach ($deptBlock['roles'] as $roleItem) {
                        $roleId = $roleItem['value'];
                         $role = Role::where('id', $roleId)
                                ->where('lab_id', $ctx['lab_id'])
                                ->firstOrFail();

                        $user->assignRole($role);
                        $permissions = $role->permissions->pluck('name')->toArray();
                        $user->syncPermissions($permissions);

                        $lab_location_department =   LabLocationDepartment::firstOrCreate([
                                'location_id' =>$roleBlock['location_id'],
                                'department_id' => $deptBlock['department_id'] ?? null,
                            ]);

                        LabUserAccess::create([
                        'lab_user_id' => $labuser->id,
                        'location_id' => $roleBlock['location_id'] ,
                        'lab_location_department_id' => $lab_location_department->id,
                        'role_id' => $role->id,
                        'status' => 'active',
                        'granted_at' => now(),
                    ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'User created successfully',
                'data'    => $user,
                'success' => true
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create user.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id, Request $request)
    {
        $ctx = $this->labContext($request);

        $query = User::with([
            'roles',
            'labUsers.accesses.role',
            'labUsers.accesses.location.cluster.zone',
            'labUsers.accesses.department.department',
        ]);

        // ✅ Lab filter
        if ($ctx['lab_id'] != 0) {
            $query->whereIn('id', function ($q) use ($ctx) {
                $q->select('user_id')
                ->from('lab_users')
                ->where('lab_id', $ctx['lab_id']);
            });
        }

        $user = $query->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $role = $user->roles->first();

        $response = [
            'id'        => $user->id,
            'name'      => $user->name,
            'username'  => $user->username,
            'email'     => $user->email,
            'dialCode'  => $user->dial_code,
            'phone'     => $user->phone,
            'address'   => $user->address,
            'city'      => $user->city,
            'postcode'  => $user->postcode,
            'profileImage' => $user->profile_image,
            'signature' => $user->signature,
            'role'      => optional($role)->id,
        ];

        if ($ctx['lab_id'] != 0) {

            $accesses = $user->labUsers->flatMap->accesses;

            $userRoles = $accesses
                ->groupBy('location_id')
                ->map(function ($locationAccesses) {

                    $first = $locationAccesses->first();

                    return [
                        'zone_id'     => optional($first->location->cluster->zone)->id,
                        'cluster_id'  => optional($first->location->cluster)->id,
                        'location_id' => $first->location_id,

                        'department' => $locationAccesses
                            ->whereNotNull('lab_location_department_id')
                            ->groupBy('lab_location_department_id')
                            ->map(function ($deptAccesses) {
                                return [
                                    'department_id' => optional($deptAccesses->first()->department->department)->id,
                                    'roles' => $deptAccesses->map(function ($access) {
                                        return [
                                            'value' => $access->role->id
                                        ];
                                    })->values()
                                ];
                            })->values()
                    ];
                })->values();

            // ✅ Attach only here
            $response['userRoles'] = $userRoles;
        }

        return response()->json(['data' => $response]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
