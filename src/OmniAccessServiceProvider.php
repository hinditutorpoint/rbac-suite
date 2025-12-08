<?php

namespace RbacSuite\OmniAccess;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use RbacSuite\OmniAccess\Commands\InstallCommand;
use RbacSuite\OmniAccess\Commands\ClearCacheCommand;
use RbacSuite\OmniAccess\Services\CacheService;
use RbacSuite\OmniAccess\Services\ValidationService;
use RbacSuite\OmniAccess\Middleware\RoleMiddleware;
use RbacSuite\OmniAccess\Middleware\PermissionMiddleware;
use RbacSuite\OmniAccess\Middleware\RoleOrPermissionMiddleware;

class OmniAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/omni-access.php', 'omni-access');
        
        // Register services
        $this->app->singleton(CacheService::class);
        $this->app->singleton(ValidationService::class);
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

            $this->commands([
                InstallCommand::class,
                ClearCacheCommand::class,
            ]);

            $this->publishMigrations();
        }

        $this->registerMiddleware();
        $this->registerBladeDirectives();
        $this->registerObservers();
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }

    /**
     * Publish migrations.
     */
    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'permission-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function registerBladeDirectives(): void
    {
        Blade::if('role', fn($role) => auth()->check() && auth()->user()->hasRole($role));
        Blade::if('permission', fn($permission) => auth()->check() && auth()->user()->hasPermission($permission));
        Blade::if('hasanyrole', fn($roles) => auth()->check() && auth()->user()->hasAnyRole(...explode('|', $roles)));
        Blade::if('hasallroles', fn($roles) => auth()->check() && auth()->user()->hasAllRoles(...explode('|', $roles)));
    }

    protected function registerObservers(): void
    {
        \RbacSuite\OmniAccess\Models\Role::observe(\RbacSuite\OmniAccess\Observers\RoleObserver::class);
        \RbacSuite\OmniAccess\Models\Permission::observe(\RbacSuite\OmniAccess\Observers\PermissionObserver::class);
        \RbacSuite\OmniAccess\Models\Group::observe(\RbacSuite\OmniAccess\Observers\GroupObserver::class);
    }
}