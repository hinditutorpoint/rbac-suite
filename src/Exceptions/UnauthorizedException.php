<?php

namespace RbacSuite\OmniAccess\Exceptions;

use Exception;
use Illuminate\Http\Request;

class UnauthorizedException extends Exception
{
    protected int $statusCode;
    protected array $requiredItems;
    protected string $type; // 'role', 'permission', 'role_or_permission'
    protected ?string $guard;

    public function __construct(
        string $message = 'Unauthorized',
        int $statusCode = 403,
        string $type = 'permission',
        array $requiredItems = [],
        ?string $guard = null
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->type = $type;
        $this->requiredItems = $requiredItems;
        $this->guard = $guard;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getRequiredItems(): array
    {
        return $this->requiredItems;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getGuard(): ?string
    {
        return $this->guard;
    }

    /**
     * Render the exception
     */
    public function render(Request $request)
    {
        return app(\RbacSuite\OmniAccess\Services\UnauthorizedResponseService::class)
            ->handle($request, $this);
    }

    /**
     * Create for missing role
     */
    public static function forRoles(array $roles, ?string $guard = null): self
    {
        $message = config('omni-access.middleware.unauthorized.messages.forbidden_role');
        $statusCode = config('omni-access.middleware.unauthorized.status_code.forbidden', 403);

        return new self($message, $statusCode, 'role', $roles, $guard);
    }

    /**
     * Create for missing permission
     */
    public static function forPermissions(array $permissions, ?string $guard = null): self
    {
        $message = config('omni-access.middleware.unauthorized.messages.forbidden_permission');
        $statusCode = config('omni-access.middleware.unauthorized.status_code.forbidden', 403);

        return new self($message, $statusCode, 'permission', $permissions, $guard);
    }

    /**
     * Create for missing role or permission
     */
    public static function forRolesOrPermissions(array $rolesOrPermissions, ?string $guard = null): self
    {
        $message = config('omni-access.middleware.unauthorized.messages.forbidden_permission');
        $statusCode = config('omni-access.middleware.unauthorized.status_code.forbidden', 403);

        return new self($message, $statusCode, 'role_or_permission', $rolesOrPermissions, $guard);
    }

    /**
     * Create for unauthenticated user
     */
    public static function notLoggedIn(): self
    {
        $message = config('omni-access.middleware.unauthorized.messages.unauthenticated');
        $statusCode = config('omni-access.middleware.unauthorized.status_code.unauthenticated', 401);

        return new self($message, $statusCode, 'unauthenticated');
    }

    /**
     * Create for missing trait
     */
    public static function missingTrait(): self
    {
        $message = config('omni-access.middleware.unauthorized.messages.missing_trait');
        $statusCode = config('omni-access.middleware.unauthorized.status_code.missing_trait', 500);

        return new self($message, $statusCode, 'missing_trait');
    }

    /**
     * Create for invalid guard
     */
    public static function invalidGuard(string $guard): self
    {
        $message = config('omni-access.middleware.unauthorized.messages.invalid_guard');
        $statusCode = config('omni-access.middleware.unauthorized.status_code.invalid_guard', 403);

        return new self($message, $statusCode, 'invalid_guard', [], $guard);
    }
}