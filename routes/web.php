<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

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

// ─── 2FA challenge (must be logged in, but not yet 2FA-verified) ──────────────

Route::middleware(['auth'])->group(function () {
    Route::get('/two-factor', [TwoFactorController::class, 'create'])->name('auth.two-factor');
    Route::post('/two-factor', [TwoFactorController::class, 'store'])->name('auth.two-factor.store');
});

// ─── Main application (authenticated + 2FA verified) ─────────────────────────

Route::middleware(['auth', 'two-factor'])->group(function () {

    // Logout
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Dashboard — accessible to Admin and Manager (hard 403 for Inventory Staff)
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // ─── 2FA account settings (Admin only) ───────────────────────────────────
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/settings', fn () => view('account.settings'))->name('settings');
        Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
        Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable'])
            ->middleware('password.confirm')
            ->name('two-factor.disable');
    });

    // ─── Module routes — uncomment as each phase completes ───────────────────

    // Phase 2: Project Management
    // require __DIR__ . '/modules/projects.php';

    // Phase 3A: Resource Allocation
    // require __DIR__ . '/modules/resources.php';

    // Phase 3B: Manpower Management
    // require __DIR__ . '/modules/manpower.php';

    // Phase 3C: Scheduling
    // require __DIR__ . '/modules/scheduling.php';

    // Phase 4: Project Costing
    // require __DIR__ . '/modules/costing.php';

    // Phase 5: Reports & Dashboard
    // require __DIR__ . '/modules/reports.php';

    // Phase 5C: Equipment Records
    // require __DIR__ . '/modules/equipment.php';

    // Phase 6: Decision Support
    // require __DIR__ . '/modules/decision-support.php';
});
