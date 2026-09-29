<?php

use App\Http\Controllers\DecisionSupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('decision-support')->name('decision-support.')->group(function () {
    Route::get('/', [DecisionSupportController::class, 'index'])->name('index');
});
