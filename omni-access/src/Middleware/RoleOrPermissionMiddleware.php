<?php

namespace RbacSuite\OmniAccess\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleOrPermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$rolesOrPermissions)
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Unauthorized access'], 401);
            }
            abort(403, 'Unauthorized access');
        }

        $user = Auth::user();

        if (!method_exists($user, 'hasRole') || !method_exists($user, 'hasPermission')) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'User model must use HasRoles trait'], 500);
            }
            abort(500, 'User model must use HasRoles trait');
        }

        $hasAccess = false;

        foreach ($rolesOrPermissions as $roleOrPermission) {
            if ($user->hasRole($roleOrPermission) || $user->hasPermission($roleOrPermission)) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Insufficient privileges'], 403);
            }
            abort(403, 'Unauthorized - Insufficient privileges');
        }

        return $next($request);
    }
}