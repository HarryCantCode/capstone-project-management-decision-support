<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Models\Notification;
use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectFile;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
use App\Models\StaffPendingAction;
use App\Models\User;
use App\Policies\ProjectPolicy;
use App\Policies\ResourcePolicy;
use App\Policies\StaffPendingActionPolicy;
use App\Policies\UserPolicy;
use App\Services\LiveUpdateService;
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

        $this->app['router']->aliasMiddleware(
            'must-change-password',
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
        );

        $this->app['router']->aliasMiddleware('role', \Spatie\Permission\Middleware\RoleMiddleware::class);
        $this->app['router']->aliasMiddleware('permission', \Spatie\Permission\Middleware\PermissionMiddleware::class);
        $this->app['router']->aliasMiddleware('role_or_permission', \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class);

        // Policy registrations
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Resource::class, ResourcePolicy::class);
        Gate::policy(StaffPendingAction::class, StaffPendingActionPolicy::class);

        $this->registerLiveUpdateHooks();
    }

    /**
     * Automatically bump live-update version tokens when core models change.
     */
    private function registerLiveUpdateHooks(): void
    {
        $bump = static function (string $domain): void {
            app(LiveUpdateService::class)->bump($domain);
        };

        foreach ([Project::class, ProjectCost::class] as $softDeleteModel) {
            $softDeleteModel::saved(fn () => $bump('projects'));
            $softDeleteModel::deleted(fn () => $bump('projects'));
            $softDeleteModel::restored(fn () => $bump('projects'));
        }

        foreach ([ProjectTask::class, ProjectSubtask::class, ProjectFile::class] as $projectModel) {
            $projectModel::saved(fn () => $bump('projects'));
            $projectModel::deleted(fn () => $bump('projects'));
        }

        StaffPendingAction::saved(fn () => $bump('pending_actions'));
        StaffPendingAction::deleted(fn () => $bump('pending_actions'));

        foreach ([Resource::class, Personnel::class] as $resourceModel) {
            $resourceModel::saved(fn () => $bump('resources'));
            $resourceModel::deleted(fn () => $bump('resources'));
            $resourceModel::restored(fn () => $bump('resources'));
        }

        Notification::saved(fn (Notification $n) => app(LiveUpdateService::class)->bumpNotifications($n->user_id));
        Notification::deleted(fn (Notification $n) => app(LiveUpdateService::class)->bumpNotifications($n->user_id));
    }
}
