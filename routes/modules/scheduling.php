<?php

use App\Http\Controllers\SchedulingController;
use Illuminate\Support\Facades\Route;

Route::prefix('scheduling')->name('scheduling.')->group(function () {
    Route::get('/', [SchedulingController::class, 'index'])->name('index');
    Route::get('/roster', [SchedulingController::class, 'roster'])->name('roster');
    Route::get('/create', [SchedulingController::class, 'create'])->name('create');
    Route::post('/', [SchedulingController::class, 'store'])->name('store');
    Route::post('/bulk-reassign', [SchedulingController::class, 'bulkReassign'])->name('bulk-reassign');
    Route::put('/{personnel}/reassign', [SchedulingController::class, 'reassign'])->name('reassign');
    Route::get('/{personnel}/edit', [SchedulingController::class, 'edit'])->name('edit')->withTrashed();
    Route::put('/{personnel}', [SchedulingController::class, 'update'])->name('update')->withTrashed();
    Route::delete('/{personnel}', [SchedulingController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/restore', [SchedulingController::class, 'restore'])->name('restore');
});
