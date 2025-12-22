<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\LabUser;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email','password');

        if(!$token = JWTAuth::attempt($credentials)){
            return response()->json(['message'=>'Invalid credentials'], 401);
        }

        $user = auth()->user();

        $labUser = LabUser::with('lab')->where('user_id', $user->id)->first();
        if ($labUser) {
            $user->lab = $labUser->lab; // returns the whole lab object (id, name, etc.)
        } else {
            $user->lab = null;
        }
        $user->load(['assignments.location.cluster.zone', 'assignments.department', 'assignments.role']);

        // If super admin -> do NOT build the hierarchy
        if ($user->is_super_admin) {
            $user->roles_structure = [];   // Or null
        } else {
            $rolesStructure = $user->assignments
                ->groupBy('location_id')
                ->map(function ($locationGroup) {

                    $first = $locationGroup->first();

                    $departments = $locationGroup
                        ->groupBy('department_id')
                        ->map(function($deptGroup) {
                            $roles = $deptGroup->map(function($assign) {
                                return [
                                    'role_id'   => $assign->role->id,
                                    'role_name' => $assign->role->name
                                ];
                            })->values();

                            return [
                                'department_id'   => $deptGroup->first()->department->id,
                                'department_name' => $deptGroup->first()->department->name,
                                'roles' => $roles
                            ];
                        })->values();

                    return [
                        'location_id'   => $first->location->id,
                        'location_name' => $first->location->name,

                        'cluster_id'    => $first->location->cluster->id,
                        'cluster_name'  => $first->location->cluster->name,

                        'zone_id'       => $first->location->cluster->zone->id,
                        'zone_name'     => $first->location->cluster->zone->name,

                        'departments'   => $departments
                    ];
                })->values();

            $user->roles_structure = $rolesStructure;
        }

        return response()->json([
            'user'  => $user,
            'token' => $token
        ]);
    }


    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message'=>'Logged out successfully']);
    }

     public function refresh()
    {
        $token = JWTAuth::refresh(JWTAuth::getToken());

        return response()->json([
            'token'=> $token,
            // 'token_type'=> 'bearer',
            // 'expires_in'=> auth()->factory()->getTTL() * 60
        ]);
    }

    public function profile()
    {
        return response()->json(auth()->user());
    }
}
