<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\PermissionRegistrar;
use App\Models\UserAssignment;
use App\Models\LabUser;

class ResolveUserTeam
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }
        $labId = (int) $request->header('X-Lab-Id');

        app(PermissionRegistrar::class)
            ->setPermissionsTeamId($labId);

        return $next($request);
    }
}
