<?php

use App\Http\Controllers\StaffApprovalListController;
use App\Http\Controllers\StaffPendingActionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff Pending Actions & Approval List Routes
|--------------------------------------------------------------------------
| Loaded inside routes/web.php within auth + 2fa + password middleware group.
*/

// Admin / Manager: Staff Request Approvals
Route::prefix('pending-actions')->name('pending-actions.')->group(function () {
    Route::get('/', [StaffPendingActionController::class, 'index'])->name('index');
    Route::get('/{pendingAction}', [StaffPendingActionController::class, 'show'])->name('show');
    Route::post('/{pendingAction}/approve', [StaffPendingActionController::class, 'approve'])->name('approve');
    Route::post('/{pendingAction}/reject', [StaffPendingActionController::class, 'reject'])->name('reject');
});

// Staff: Approval List (View submitted requests & ping reviewers)
Route::prefix('approval-list')->name('approval-list.')->group(function () {
    Route::get('/', [StaffApprovalListController::class, 'index'])->name('index');
    Route::get('/{pendingAction}', [StaffApprovalListController::class, 'show'])->name('show');
    Route::post('/{pendingAction}/ping', [StaffApprovalListController::class, 'ping'])->name('ping');
});
