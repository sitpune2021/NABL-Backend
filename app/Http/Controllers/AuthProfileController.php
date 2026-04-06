<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\PermissionRegistrar;
use App\Models\{User, Lab};

class AuthProfileController extends Controller
{

    public function profile()
    {
        $user = auth()->user();

        // ✅ Load BOTH
        $rolesCollection = $user->rolesWithLab()->get();

        $user->load([
            'labUsers.lab',
            'labUsers.accesses.role',
            'labUsers.accesses.location',
            'labUsers.accesses.department.department',
        ]);

        $labIds = $rolesCollection->pluck('lab_id')
            ->filter(fn($id) => $id != 0)
            ->unique();

        $labs = Lab::whereIn('id', $labIds)
            ->get()
            ->keyBy('id');

        $roles = $rolesCollection
            ->groupBy('lab_id')
            ->map(function ($group) use ($user, $labs) {

                $labId = $group->first()->lab_id;
                $lab   = $labs[$labId] ?? null;

                // ✅ GET ACCESSES FOR THIS LAB
                $labUser = $user->labUsers->firstWhere('lab_id', $labId);
                $accesses = $labUser?->accesses ?? collect();

                // ✅ GROUP BY LOCATION
                $locations = $accesses->groupBy('location_id')->map(function ($locationAccesses) {

                    $location = optional($locationAccesses->first()->location);

                    // LOCATION ROLES
                    $locationRoles = $locationAccesses
                        ->whereNull('lab_location_department_id')
                        ->map(fn($a) => [
                            'id' => $a->role->id,
                            'name' => $a->role->name,
                        ])
                        ->values();

                    // DEPARTMENT ROLES
                    $departments = $locationAccesses
                        ->whereNotNull('lab_location_department_id')
                        ->groupBy('lab_location_department_id')
                        ->map(function ($deptAccesses) {

                            $department = optional($deptAccesses->first()->department);

                            return [
                                'id' => $department?->id,
                                'name' => $department?->department?->name,
                                'roles' => $deptAccesses->map(fn($a) => [
                                    'id' => $a->role->id,
                                    'name' => $a->role->name,
                                ])->values()
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
                    'lab_id'   => $labId,
                    'lab_name' => $group->first()->lab_name ?? 'Master',

                    // ✅ KEEP YOUR EXISTING ROLE STRUCTURE
                    'roles' => $group->map(function ($role) {
                        return [
                            'id'          => $role->id,
                            'name'        => $role->name,
                            'level'       => $role->level,
                            'description' => $role->description,
                            'permissions' => $role->permissions
                                ->pluck('name')
                                ->values()
                                ->push('home', 'accessDenied'),
                        ];
                    })->values(),

                    // ✅ NEW: ACCESS-BASED STRUCTURE
                    'locations' => ($labId != 0 && !$user->is_super_admin)
                        ? $locations
                        : [],

                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'data'   => [
                'roles' => $roles
            ]
        ]);
    }

    public function show()
    {
        $user = User::with([
            'assignments.location.cluster.zone',
            'assignments.department',
            'assignments.role'
        ])->find(auth()->id());

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        $userRoles = $user->assignments->groupBy('location_id')->map(function($assignmentsGroup) {
            $first = $assignmentsGroup->first();

            return [
                'location_id'   => $first->location_id,
                'location_name' => $first->location->name ?? 'N/A',
                'zone_id'       => $first->location->cluster->zone->id ?? null,
                'cluster_id'    => $first->location->cluster->id ?? null,
                'department'    => $assignmentsGroup->groupBy('department_id')->map(function($deptAssignments) {
                    $deptFirst = $deptAssignments->first();

                    return [
                        'department_id'   => $deptFirst->department_id,
                        'department_name' => $deptFirst->department->name ?? 'N/A',
                        'roles' => $deptAssignments->map(function($uldr) {
                            return [
                                'value' => $uldr->role->id,
                                'label' => $uldr->role->name,
                            ];
                        })->values()
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'username'     => $user->username,
                'email'        => $user->email,
                'phone'        => $user->phone,
                'dialCode'     => $user->dial_code,
                'address'      => $user->address,
                'signature'    => $user->signature,
                'profileImage' => $user->profile_image ?? '',
                'userRoles'    => $userRoles
            ]
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'phone'    => 'nullable|string',
            'dialCode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Basic Info Update
            $user->update([
                'name'      => $request->name,
                'address'   => $request->address,
                'signature' => $request->signature,
            ]);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Profile updated successfully',
                'data'    => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Update failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
