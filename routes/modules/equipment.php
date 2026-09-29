<?php

use App\Http\Controllers\EquipmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('equipment')->name('equipment.')->group(function () {
    Route::get('/', [EquipmentController::class, 'index'])->name('index');
    Route::put('/{equipment}', [EquipmentController::class, 'update'])->name('update');
});
