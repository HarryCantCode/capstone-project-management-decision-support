<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Web Routes — Dex PMS
|--------------------------------------------------------------------------
|
| Route structure follows the module map in architecture.md.
|
| All routes are behind session authentication except the login routes.
| The 'two-factor' middleware ensures Admin users complete TOTP before
| accessing the main application.
|
| Module-specific route files are loaded from routes/modules/ and
| registered here once their phases are complete.
|
*/

// ─── Guest-only routes (redirect authenticated users to dashboard) ────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

// ─── 2FA challenge & First-login password change (must be logged in) ──────────

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/two-factor', [TwoFactorController::class, 'create'])->name('auth.two-factor');
    Route::post('/two-factor', [TwoFactorController::class, 'store'])->name('auth.two-factor.store');

    Route::get('/first-login/change-password', [\App\Http\Controllers\Auth\FirstLoginPasswordController::class, 'create'])->name('password.first-login');
    Route::post('/first-login/change-password', [\App\Http\Controllers\Auth\FirstLoginPasswordController::class, 'update'])->name('password.first-login.update');
});

// ─── Main application (authenticated + 2FA verified + password rotated) ──────

Route::middleware(['auth', 'two-factor', 'must-change-password'])->group(function () {

    // Dashboard — accessible to Admin and Manager (hard 403 for Inventory Staff)
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Global real-time search across Projects, Manpower, and Resources
    Route::get('/global-search', [\App\Http\Controllers\GlobalSearchController::class, 'search'])->name('global-search');

    // ─── Account settings & User management ─────────────────────────────────
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/settings', function () {
            $activeUsers = auth()->user()->hasRole('Admin') ? \App\Models\User::with('roles')->withoutTrashed()->get() : collect();
            $archivedUsers = auth()->user()->hasRole('Admin') ? \App\Models\User::with('roles')->onlyTrashed()->get() : collect();

            return view('account.settings', compact('activeUsers', 'archivedUsers'));
        })->name('settings');

        // Self-service profile updates (email & password for all authenticated users)
        Route::put('/profile/email', [\App\Http\Controllers\ProfileController::class, 'updateEmail'])->name('profile.email');
        Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');

        Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
        Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable'])
            ->middleware('password.confirm')
            ->name('two-factor.disable');

        // User Management & Account Archiving (Admin only)
        Route::post('users/{id}/restore', [\App\Http\Controllers\AccountUserController::class, 'restore'])->name('users.restore');
        Route::post('users/{user}/unlock', [\App\Http\Controllers\AccountUserController::class, 'unlock'])->name('users.unlock');
        Route::resource('users', \App\Http\Controllers\AccountUserController::class)->except(['index', 'show']);
    });

    // ─── Module routes — uncomment as each phase completes ───────────────────

    // Phase 2: Project Management
    require __DIR__ . '/modules/projects.php';

    // Phase 3A: Resource Allocation
    require __DIR__ . '/modules/resources.php';

    // Phase 3B: Manpower Management
    // require __DIR__ . '/modules/manpower.php';

    // Phase 3C: Scheduling
    require __DIR__ . '/modules/scheduling.php';

    // Phase 4: Project Costing
    require __DIR__ . '/modules/costing.php';

    // Phase 5: Reports & Dashboard
    require __DIR__ . '/modules/reports.php';

    // Phase 6: Decision Support
    require __DIR__ . '/modules/decision-support.php';
});
