<?php

use App\Http\Controllers\StaffPendingActionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff Pending Actions Approval Routes
|--------------------------------------------------------------------------
| Loaded inside routes/web.php within auth + 2fa + password middleware group.
*/

Route::prefix('pending-actions')->name('pending-actions.')->group(function () {
    Route::get('/{pendingAction}', [StaffPendingActionController::class, 'show'])->name('show');
    Route::post('/{pendingAction}/approve', [StaffPendingActionController::class, 'approve'])->name('approve');
    Route::post('/{pendingAction}/reject', [StaffPendingActionController::class, 'reject'])->name('reject');
});
