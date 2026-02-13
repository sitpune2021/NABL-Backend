<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\UserCustomPermission;
use App\Http\Requests\StoreUserRequest;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use App\Models\{Lab, LabLocation, LabLocationDepartment, LabUser, Contact, User, UserLocationDepartmentRole};
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
                'assignments.location',
                'assignments.department',
                'assignments.role',
                'assignments.customPermissions.permission'
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
                $locations = $user->assignments->groupBy('location_id')->map(function ($locationAssignments) {
                    $location = $locationAssignments->first()->location;
                    $departments = $locationAssignments->groupBy('department_id')->map(function ($deptAssignments) {
                        $department = $deptAssignments->first()->department;
                        $roles = $deptAssignments->map(function ($uldr) {
                            return [
                                'id' => $uldr->role->id,
                                'name' => $uldr->role->name,
                                'permissions' => $uldr->customPermissions->pluck('permission.name')->toArray(),
                            ];
                        })->values();
                        return [
                            'id' => $department->id,
                            'name' => $department->name,
                            'roles' => $roles
                        ];
                    })->values();
                    return [
                        'id' => $location->id,
                        'name' => $location->name,
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
            ]);

                        $ctx = $this->labContext($request);


            if($ctx['lab_id'] == 0) {
                $Role = Role::where('id', $request->role)
                                ->where('lab_id', $ctx['lab_id'])
                                ->firstOrFail();

                $user->syncRoles([$Role]);
                $permissions = $Role->permissions->pluck('name')->toArray();
                $user->syncPermissions($permissions);
            }

            // 2️⃣ Assign roles
            foreach ($request->userRoles as $roleBlock) {
                foreach ($roleBlock['department'] as $deptBlock) {
                    foreach ($deptBlock['roles'] as $roleItem) {
                        $roleId = $roleItem['value'];
                        $role = Role::find($roleId);
                        $user->syncRoles([$role]);
                        $permissions = $role->permissions->pluck('name')->toArray();
                        $user->syncPermissions($permissions);

                        $uldr = UserLocationDepartmentRole::create([
                            'user_id'       => $user->id,
                            'location_id'   => $roleBlock['location_id'],
                            'department_id' => $deptBlock['department_id'],
                            'role_id'       => $roleId,
                            'status'        => 'active',
                            'position_type' => 'permanent',
                        ]);
                        
                    }
                }
            }

            $authLab = LabUser::where('user_id', $authUser->id)->first();
            if ($authLab) {
                LabUser::create([
                    'user_id' => $user->id,
                    'lab_id'  => $authLab->lab_id,
                ]);
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
    public function show($id)
    {
        $user = User::with([
            'assignments' => function ($q) {
                $q->with([
                    'location',
                    'department',
                    'role',
                    'customPermissions.permission'
                ]);
            }
        ])->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        
        $role = $user->roles->first(); // Spatie role

        $userRoles = $user->assignments->groupBy(function($assignment) {
            return $assignment->location_id;
        })->map(function($assignmentsGroup) {

            $first = $assignmentsGroup->first();
            $zone_id     = $first->location->cluster->zone->id;
            $cluster_id  = $first->location->cluster->id;
            $location_id = $first->location_id;

            $departments = $assignmentsGroup->map(function($uldr) {
                $roles = [
                    [
                        'value' => $uldr->role->id,
                        'label' => $uldr->role->name
                    ]
                ];

                return [
                    'department_id' => $uldr->department_id,
                    'roles'         => $roles,
                ];
            })->values();

            return [
                'location_id' => $location_id,
                'zone_id'     => $zone_id,
                'cluster_id'  => $cluster_id,
                'department'  => $departments
            ];
        })->values();

        $response = [
            'id'        => $user->id,
            'name'      => $user->name,
            'username'  => $user->username,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'dialCode'  => $user->dial_code,
            'address'   => $user->address,
            'signature' => $user->signature,
            'role'      => $role ? $role->id : null,
            'userRoles' => $userRoles
        ];

        return response()->json(['data'=>$response]);
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
