<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\{User};

class AuthProfileController extends Controller
{
   public function show()
{
    $user = User::with([
        'assignments.location.cluster.zone',
        'assignments.department',
        'assignments.role',
        'assignments.customPermissions.permission'
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
                            'permissions' => $uldr->customPermissions->map(function($cp) {
                                return $cp->permission->name;
                            })->values()
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
            'username' => 'required|string|unique:users,username,' . $user->id,
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
                'username'  => $request->username,
                'email'     => $request->email,
                'phone'     => $request->phone,
                'dial_code' => $request->dialCode,
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
