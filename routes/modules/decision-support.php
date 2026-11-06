<?php

use App\Http\Controllers\DecisionSupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('decision-support')->name('decision-support.')->group(function () {
    Route::get('/', [DecisionSupportController::class, 'index'])->name('index');
    Route::post('/simulate', [DecisionSupportController::class, 'simulate'])->name('simulate');
    Route::post('/allocate', [DecisionSupportController::class, 'allocate'])->name('allocate');
});
