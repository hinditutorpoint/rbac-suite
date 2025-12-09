<?php

namespace RbacSuite\OmniAccess\Middleware;

use Closure;
use Illuminate\Http\Request;
use RbacSuite\OmniAccess\Traits\HandlesAuthorization;
use RbacSuite\OmniAccess\Exceptions\UnauthorizedException;

class PermissionMiddleware
{
    use HandlesAuthorization;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$params  Permissions (string, comma-separated, pipe-separated) and optional guard
     * @return mixed
     *
     * @throws \RbacSuite\OmniAccess\Exceptions\UnauthorizedException
     * 
     * Usage:
     * - Route::middleware('permission:create-posts')
     * - Route::middleware('permission:create-posts,edit-posts')
     * - Route::middleware('permission:create-posts|edit-posts')
     * - Route::middleware('permission:create-posts,guard:api')
     */
    public function handle(Request $request, Closure $next, ...$params)
    {
        // Parse parameters
        $parsed = $this->parseParameters($params);
        $permissions = $parsed['items'];
        $guard = $parsed['guard'];

        // Check if permissions are provided
        if (empty($permissions)) {
            return $next($request);
        }

        // Get authenticated user
        $user = $this->getAuthenticatedUser($request, $guard);

        // Check if user is authenticated
        if (!$user) {
            throw UnauthorizedException::notLoggedIn();
        }

        // Check if user model has required trait
        if (!$this->userHasTrait($user)) {
            throw UnauthorizedException::missingTrait();
        }

        // Resolve guard to use
        $resolvedGuard = $this->resolveGuard($user, $guard);

        // Check if user has any of the required permissions
        if (!$user->hasAnyPermission(...$permissions)) {
            throw UnauthorizedException::forPermissions($permissions, $resolvedGuard);
        }

        return $next($request);
    }
}