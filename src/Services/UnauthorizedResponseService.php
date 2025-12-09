<?php

namespace RbacSuite\OmniAccess\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use RbacSuite\OmniAccess\Exceptions\UnauthorizedException;

class UnauthorizedResponseService
{
    /**
     * Handle unauthorized response
     */
    public function handle(Request $request, UnauthorizedException $exception): Response|JsonResponse|RedirectResponse
    {
        $responseType = $this->getResponseType($request);

        return match ($responseType) {
            'json' => $this->jsonResponse($request, $exception),
            'view' => $this->viewResponse($exception),
            'redirect' => $this->redirectResponse($exception),
            'abort' => $this->abortResponse($exception),
            default => $this->autoResponse($request, $exception),
        };
    }

    /**
     * Get response type from config
     */
    protected function getResponseType(Request $request): string
    {
        $type = config('omni-access.middleware.unauthorized.response_type', 'auto');

        if ($type === 'auto') {
            return $request->expectsJson() ? 'json' : 'abort';
        }

        return $type;
    }

    /**
     * Auto-detect response type
     */
    protected function autoResponse(Request $request, UnauthorizedException $exception): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->jsonResponse($request, $exception);
        }

        return $this->abortResponse($exception);
    }

    /**
     * JSON response
     */
    protected function jsonResponse(Request $request, UnauthorizedException $exception): JsonResponse
    {
        $config = config('omni-access.middleware.unauthorized.json', []);
        
        $data = [
            'success' => false,
            'message' => $exception->getMessage(),
            'error' => [
                'type' => $exception->getType(),
                'status_code' => $exception->getStatusCode(),
            ],
        ];

        // Include required items if configured
        if ($config['include_required'] ?? false) {
            $data['error']['required'] = $exception->getRequiredItems();
        }

        // Include user roles if configured
        if (($config['include_user_roles'] ?? false) && auth()->check()) {
            $user = auth()->user();
            if (method_exists($user, 'roles')) {
                $data['error']['user_roles'] = $user->roles->pluck('slug')->toArray();
            }
        }

        // Include guard if present
        if ($exception->getGuard()) {
            $data['error']['guard'] = $exception->getGuard();
        }

        return response()->json($data, $exception->getStatusCode());
    }

    /**
     * View response
     */
    protected function viewResponse(UnauthorizedException $exception): Response
    {
        $config = config('omni-access.middleware.unauthorized.view', []);
        
        $viewName = $config['name'] ?? 'errors.unauthorized';
        $layout = $config['layout'] ?? null;
        $additionalData = $config['data'] ?? [];

        $data = array_merge([
            'exception' => $exception,
            'message' => $exception->getMessage(),
            'type' => $exception->getType(),
            'required' => $exception->getRequiredItems(),
            'guard' => $exception->getGuard(),
        ], $additionalData);

        // Check if view exists
        if (!view()->exists($viewName)) {
            return $this->abortResponse($exception);
        }

        $view = view($viewName, $data);

        if ($layout && view()->exists($layout)) {
            $view = $view->layout($layout);
        }

        return response($view, $exception->getStatusCode());
    }

    /**
     * Redirect response
     */
    protected function redirectResponse(UnauthorizedException $exception): RedirectResponse
    {
        $config = config('omni-access.middleware.unauthorized.redirect', []);
        
        $url = $config['url'] ?? '/login';
        $routeName = $config['route'] ?? null;
        $withMessage = $config['with_message'] ?? true;
        $messageKey = $config['message_key'] ?? 'error';

        // Use route name if provided
        if ($routeName && \Route::has($routeName)) {
            $url = route($routeName);
        }

        $redirect = redirect($url);

        if ($withMessage) {
            $redirect->with($messageKey, $exception->getMessage());
        }

        return $redirect;
    }

    /**
     * Abort response
     */
    protected function abortResponse(UnauthorizedException $exception): Response
    {
        abort($exception->getStatusCode(), $exception->getMessage());
    }
}