<?php

namespace RbacSuite\OmniAccess\Middleware;

use Closure;
use Illuminate\Http\Request;
use RbacSuite\OmniAccess\Traits\HandlesAuthorization;
use RbacSuite\OmniAccess\Exceptions\UnauthorizedException;

class RoleOrPermissionMiddleware
{
    use HandlesAuthorization;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$params  Roles or Permissions and optional guard
     * @return mixed
     *
     * @throws \RbacSuite\OmniAccess\Exceptions\UnauthorizedException
     * 
     * Usage:
     * - Route::middleware('role_or_permission:admin,create-posts')
     * - Route::middleware('role_or_permission:admin|editor|create-posts')
     */
    public function handle(Request $request, Closure $next, ...$params)
    {
        // Parse parameters
        $parsed = $this->parseParameters($params);
        $rolesOrPermissions = $parsed['items'];
        $guard = $parsed['guard'];

        // Check if roles/permissions are provided
        if (empty($rolesOrPermissions)) {
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

        // Check if user has any of the required roles OR permissions
        $hasAccess = $user->hasAnyRole($rolesOrPermissions, $resolvedGuard) 
                     || $user->hasAnyPermission(...$rolesOrPermissions);

        if (!$hasAccess) {
            throw UnauthorizedException::forRolesOrPermissions($rolesOrPermissions, $resolvedGuard);
        }

        return $next($request);
    }
}