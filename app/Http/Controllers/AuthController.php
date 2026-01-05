<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\LabUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!$token = JWTAuth::attempt($request->only('email', 'password'))) {
            Log::warning('Failed login attempt', ['email' => $request->email, 'ip' => $request->ip()]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = auth()->user();

        $labUser = LabUser::with('lab')->where('user_id', $user->id)->first();
        $user->lab = $labUser ? $labUser->lab : null;
        $user->load(['assignments.location.cluster.zone', 'assignments.department', 'assignments.role']);

        if ($user->is_super_admin) {
            $user->roles_structure = [];
        } else {
            $user->roles_structure = $user->assignments
                ->groupBy('location_id')
                ->map(function ($locationGroup) {
                    $first = $locationGroup->first();
                    $departments = $locationGroup
                        ->groupBy('department_id')
                        ->map(function ($deptGroup) {
                            $roles = $deptGroup->map(function ($assign) {
                                return [
                                    'role_id'   => $assign->role->id,
                                    'role_name' => $assign->role->name
                                ];
                            })->values();

                            return [
                                'department_id'   => $deptGroup->first()->department->id,
                                'department_name' => $deptGroup->first()->department->name,
                                'roles'           => $roles
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
        }

        $user->makeHidden(['password', 'remember_token']);

        Log::info('User logged in', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json([
            'success' => true,
            'user'    => $user,
            'roles'   => $user->getRoleNames(),       // Spatie roles
            'permissions' => $user->getAllPermissions()->pluck('name'), // Spatie permissions
            'token'   => $token
        ]);
    }


    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            Log::info('User logged out', ['user_id' => auth()->id()]);
            return response()->json(['success' => true, 'message' => 'Logged out successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Token invalid or expired'], 400);
        }
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            Log::info('Token refreshed', ['user_id' => auth()->id()]);
            return response()->json([
                'success' => true,
                'token'   => $token
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Unable to refresh token'], 400);
        }
    }

    public function profile()
    {
        $user = auth()->user();
        $user->makeHidden(['password', 'remember_token']);
        return response()->json(['success' => true, 'user' => $user]);
    }
}
