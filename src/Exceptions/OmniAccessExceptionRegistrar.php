<?php

namespace RbacSuite\OmniAccess\Exceptions;

use Illuminate\Foundation\Configuration\Exceptions;

class OmniAccessExceptionRegistrar
{
    public function __invoke(Exceptions $exceptions)
    {
        $exceptions->renderable(function (UnauthorizedException $e, $request) {
            return app(\RbacSuite\OmniAccess\Services\UnauthorizedResponseService::class)
                ->handle($request, $e);
        });
    }
}
