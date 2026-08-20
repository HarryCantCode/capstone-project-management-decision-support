<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Models\User;
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

        // Policy registrations
        Gate::policy(User::class, UserPolicy::class);
    }
}
