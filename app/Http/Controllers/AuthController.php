<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\LabUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;


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
        $user->makeHidden(['password', 'remember_token', 'dial_code', 'phone', 'address', 'email_verified_at', 'created_at', 'updated_at']);
        
        return $this->success([
            'user'    => $user,
            'token'   => $token
        ], 'Login successful');
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

}
