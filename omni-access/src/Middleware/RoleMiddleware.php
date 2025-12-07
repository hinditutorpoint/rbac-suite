<?php

namespace RbacSuite\OmniAccess\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! Auth::check()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Unauthorized access',
                ], 401);
            }
            abort(403, 'Unauthorized access');
        }

        $user = Auth::user();

        if (! method_exists($user, 'hasAnyRole')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'User model must use HasRoles trait',
                ], 500);
            }
            abort(500, 'User model must use HasRoles trait');
        }

        if (! $user->hasAnyRole($roles)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Unauthorized access',
                ], 403);
            }
            abort(403, 'Unauthorized - Insufficient role privileges');
        }

        return $next($request);
    }
}
