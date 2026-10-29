<?php

use App\Http\Controllers\ResourceAllocationController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

Route::resource('resources', ResourceController::class)->only(['index', 'show']);
Route::get('resources/{resource}/allocate', [ResourceAllocationController::class, 'create'])
    ->name('resources.allocate');
Route::post('resources/{resource}/allocate', [ResourceAllocationController::class, 'store'])
    ->name('resources.allocate.store');
