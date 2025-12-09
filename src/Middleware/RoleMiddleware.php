<?php

namespace RbacSuite\OmniAccess\Middleware;

use Closure;
use Illuminate\Http\Request;
use RbacSuite\OmniAccess\Traits\HandlesAuthorization;
use RbacSuite\OmniAccess\Exceptions\UnauthorizedException;

class RoleMiddleware
{
    use HandlesAuthorization;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$params  Roles (string, comma-separated, pipe-separated) and optional guard
     * @return mixed
     *
     * @throws \RbacSuite\OmniAccess\Exceptions\UnauthorizedException
     * 
     * Usage:
     * - Route::middleware('role:admin')
     * - Route::middleware('role:admin,editor')
     * - Route::middleware('role:admin|editor')
     * - Route::middleware('role:admin,editor,guard:api')
     * - Route::middleware(['role:admin', 'role:editor'])
     */
    public function handle(Request $request, Closure $next, ...$params)
    {
        // Parse parameters
        $parsed = $this->parseParameters($params);
        $roles = $parsed['items'];
        $guard = $parsed['guard'];

        // Check if roles are provided
        if (empty($roles)) {
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

        // Check if user has any of the required roles
        if (!$user->hasAnyRole($roles, $resolvedGuard)) {
            throw UnauthorizedException::forRoles($roles, $resolvedGuard);
        }

        return $next($request);
    }
}