<?php

namespace RbacSuite\OmniAccess\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RbacSuite\OmniAccess\Exceptions\UnauthorizedException;

trait HandlesAuthorization
{
    /**
     * Parse parameters from middleware (supports string, pipe, comma, array)
     * 
     * Examples:
     * - 'admin' => ['admin']
     * - 'admin,editor' => ['admin', 'editor']
     * - 'admin|editor' => ['admin', 'editor']
     * - 'admin,editor,guard:api' => ['items' => ['admin', 'editor'], 'guard' => 'api']
     */
    protected function parseParameters(array $params): array
    {
        $items = [];
        $guard = null;

        foreach ($params as $param) {
            // Check for guard parameter
            if (str_starts_with($param, 'guard:')) {
                $guard = substr($param, 6);
                continue;
            }

            // Handle pipe-separated values
            if (str_contains($param, '|')) {
                $items = array_merge($items, explode('|', $param));
                continue;
            }

            // Handle comma-separated values
            if (str_contains($param, ',')) {
                $items = array_merge($items, explode(',', $param));
                continue;
            }

            $items[] = $param;
        }

        // Clean up items
        $items = array_map('trim', $items);
        $items = array_filter($items);
        $items = array_unique($items);

        return [
            'items' => array_values($items),
            'guard' => $guard,
        ];
    }

    /**
     * Get the authenticated user for the given guard
     */
    protected function getAuthenticatedUser(Request $request, ?string $guard = null)
    {
        // If guard specified, use it
        if ($guard) {
            if (!$this->isValidGuard($guard)) {
                throw UnauthorizedException::invalidGuard($guard);
            }
            return Auth::guard($guard)->user();
        }

        // Try to get user from request
        if ($request->user()) {
            return $request->user();
        }

        // Fallback to default guard
        return Auth::user();
    }

    /**
     * Check if guard is valid
     */
    protected function isValidGuard(string $guard): bool
    {
        if (!config('omni-access.middleware.validate_guard', true)) {
            return true;
        }

        $availableGuards = config('omni-access.guards.available', []);
        $authGuards = array_keys(config('auth.guards', []));

        return in_array($guard, $availableGuards) || in_array($guard, $authGuards);
    }

    /**
     * Check if user model has required trait
     */
    protected function userHasTrait($user): bool
    {
        return method_exists($user, 'hasRole') && method_exists($user, 'hasPermission');
    }

    /**
     * Get guard from user model if available
     */
    protected function getGuardFromUser($user): ?string
    {
        if (method_exists($user, 'getGuardName')) {
            return $user->getGuardName();
        }

        if (property_exists($user, 'guard_name')) {
            return $user->guard_name;
        }

        return null;
    }

    /**
     * Resolve the guard to use
     */
    protected function resolveGuard($user, ?string $paramGuard): string
    {
        // Priority: Parameter guard > User guard > Config default > Laravel default
        if ($paramGuard) {
            return $paramGuard;
        }

        $userGuard = $this->getGuardFromUser($user);
        if ($userGuard) {
            return $userGuard;
        }

        $configGuard = config('omni-access.guards.default');
        if ($configGuard) {
            return $configGuard;
        }

        return Auth::getDefaultDriver() ?? 'web';
    }
}