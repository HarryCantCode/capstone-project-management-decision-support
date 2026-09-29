<?php

use App\Http\Controllers\SchedulingController;
use Illuminate\Support\Facades\Route;

Route::prefix('scheduling')->name('scheduling.')->group(function () {
    Route::get('/', [SchedulingController::class, 'index'])->name('index');
    Route::get('/create', [SchedulingController::class, 'create'])->name('create');
    Route::post('/', [SchedulingController::class, 'store'])->name('store');
    Route::post('/bulk-reassign', [SchedulingController::class, 'bulkReassign'])->name('bulk-reassign');
    Route::put('/{personnel}/reassign', [SchedulingController::class, 'reassign'])->name('reassign');
});
