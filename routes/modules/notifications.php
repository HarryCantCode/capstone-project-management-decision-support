<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notification Routes
|--------------------------------------------------------------------------
| Loaded from routes/web.php.
| All routes inside the 'auth + two-factor + must-change-password' middleware group.
*/

Route::prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
    Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('mark-read');
    Route::post('/{notification}/archive', [NotificationController::class, 'archive'])->name('archive');
    Route::post('/{notification}/unarchive', [NotificationController::class, 'unarchive'])->name('unarchive');
});
