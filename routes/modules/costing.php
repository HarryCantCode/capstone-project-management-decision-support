<?php

use App\Http\Controllers\CostingController;
use Illuminate\Support\Facades\Route;

Route::prefix('costing')->name('costing.')->group(function () {
    Route::get('/', [CostingController::class, 'index'])->name('index');
    Route::get('/{project}', [CostingController::class, 'show'])->name('show');
});
