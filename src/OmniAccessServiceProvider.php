<?php

namespace RbacSuite\OmniAccess;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use RbacSuite\OmniAccess\Commands\InstallCommand;
use RbacSuite\OmniAccess\Commands\ClearCacheCommand;
use RbacSuite\OmniAccess\Services\CacheService;
use RbacSuite\OmniAccess\Services\ValidationService;
use RbacSuite\OmniAccess\Services\UnauthorizedResponseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Configuration\Exceptions;
use RbacSuite\OmniAccess\Exceptions\OmniAccessExceptionRegistrar;

class OmniAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/omni-access.php', 'omni-access');
        
        // Register services
        $this->app->singleton(CacheService::class);
        $this->app->singleton(ValidationService::class);
        $this->app->singleton(UnauthorizedResponseService::class, function ($app) {
            return new UnauthorizedResponseService();
        });
        $this->app->singleton('omni-access', fn() => new OmniAccessManager);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/omni-access.php' => config_path('omni-access.php'),
            ], 'omni-access-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'omni-access-migrations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/omni-access'),
            ], 'omni-access-views');

            $this->commands([
                InstallCommand::class,
                \RbacSuite\OmniAccess\Commands\CreateRoleCommand::class,
                \RbacSuite\OmniAccess\Commands\CreatePermissionCommand::class,
                \RbacSuite\OmniAccess\Commands\AssignRoleCommand::class,
                \RbacSuite\OmniAccess\Commands\ListRolesCommand::class,
                \RbacSuite\OmniAccess\Commands\ListPermissionsCommand::class,
                \RbacSuite\OmniAccess\Commands\ShowUserRolesCommand::class,
                ClearCacheCommand::class,
            ]);
        }

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'omni-access');
        // Register middleware
        $this->registerMiddleware();
        // Register exception handler
        $this->registerExceptionHandler();
        $this->registerBladeDirectives();
        $this->registerObservers();
    }

    protected function registerMiddleware(): void
    {
        if (!config('omni-access.middleware.register', true)) {
            return;
        }

        $router = $this->app->make(Router::class);
        $aliases = config('omni-access.middleware.aliases', []);

        foreach ($aliases as $alias => $middleware) {
            $router->aliasMiddleware($alias, $middleware);
        }
    }

    protected function registerBladeDirectives(): void
    {
        /* Helper: normalize pipe/comma separated roles/permissions */
        $normalize = function ($value) {
            if (is_string($value)) {
                return preg_split('/[\|,]/', $value);
            }
            return (array) $value;
        };

        /* @role('admin') */
        Blade::if('role', function ($role) use ($normalize) {
            return Auth::check()
                && Auth::user()->hasRole(...$normalize($role));
        });

        /* @hasrole('admin') */
        Blade::if('hasrole', function ($role) use ($normalize) {
            return Auth::check()
                && Auth::user()->hasRole(...$normalize($role));
        });

        /* @unlessrole('admin') */
        Blade::if('unlessrole', function ($role) use ($normalize) {
            return Auth::check()
                && !Auth::user()->hasRole(...$normalize($role));
        });

        /* @hasanyrole('admin|editor') */
        Blade::if('hasanyrole', function ($roles) use ($normalize) {
            return Auth::check()
                && Auth::user()->hasAnyRole(...$normalize($roles));
        });

        /* @hasallroles('admin|editor') */
        Blade::if('hasallroles', function ($roles) use ($normalize) {
            return Auth::check()
                && Auth::user()->hasAllRoles(...$normalize($roles));
        });

        /* @permission('posts.create') */
        Blade::if('permission', function ($permission) use ($normalize) {
            return Auth::check()
                && Auth::user()->hasPermission(...$normalize($permission));
        });

        /* @haspermission('posts.create') */
        Blade::if('haspermission', function ($permission) use ($normalize) {
            return Auth::check()
                && Auth::user()->hasPermission(...$normalize($permission));
        });

        /* @hasanypermission('posts.create|posts.edit') */
        Blade::if('hasanypermission', function ($permissions) use ($normalize) {
            return Auth::check()
                && Auth::user()->hasAnyPermission(...$normalize($permissions));
        });

        /* @hasallpermissions('posts.create|posts.edit') */
        Blade::if('hasallpermissions', function ($permissions) use ($normalize) {
            return Auth::check()
                && Auth::user()->hasAllPermissions(...$normalize($permissions));
        });

        /* @superadmin */
        Blade::if('superadmin', function () {
            return Auth::check() && Auth::user()->isSuperAdmin();
        });
    }

    protected function registerObservers(): void
    {
        \RbacSuite\OmniAccess\Models\Role::observe(\RbacSuite\OmniAccess\Observers\RoleObserver::class);
        \RbacSuite\OmniAccess\Models\Permission::observe(\RbacSuite\OmniAccess\Observers\PermissionObserver::class);
        \RbacSuite\OmniAccess\Models\Group::observe(\RbacSuite\OmniAccess\Observers\GroupObserver::class);
    }

    /**
     * Register exception handler for UnauthorizedException
     */
    protected function registerExceptionHandler(): void
    {
        $this->app->resolving(Exceptions::class, function (Exceptions $exceptions) {
            (new OmniAccessExceptionRegistrar())($exceptions);
        });
    }
}