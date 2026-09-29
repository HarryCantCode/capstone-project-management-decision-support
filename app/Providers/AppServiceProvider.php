<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Models\Project;
use App\Models\Resource;
use App\Models\User;
use App\Policies\ProjectPolicy;
use App\Policies\ResourcePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Registers:
     *  - The 'two-factor' middleware alias used in routes/web.php
     *  - Explicit Policy registrations (Laravel auto-discovers these,
     *    but explicit registration is clearer and faster at boot)
     */
    public function boot(): void
    {
        // Register the 2FA middleware alias.
        // Used as: Route::middleware(['auth', 'two-factor'])
        $this->app['router']->aliasMiddleware(
            'two-factor',
            EnsureTwoFactorVerified::class,
        );

        $this->app['router']->aliasMiddleware('role', \Spatie\Permission\Middleware\RoleMiddleware::class);
        $this->app['router']->aliasMiddleware('permission', \Spatie\Permission\Middleware\PermissionMiddleware::class);
        $this->app['router']->aliasMiddleware('role_or_permission', \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class);

        // Policy registrations
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Resource::class, ResourcePolicy::class);
    }
}
