<?php

use App\Http\Controllers\ResourceAllocationController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

Route::delete('resources/bulk-destroy', [ResourceController::class, 'bulkDestroy'])
    ->name('resources.bulk-destroy');
Route::resource('resources', ResourceController::class)->except(['create', 'edit']);
Route::get('resources/{resource}/allocate', [ResourceAllocationController::class, 'create'])
    ->name('resources.allocate');
Route::post('resources/{resource}/allocate', [ResourceAllocationController::class, 'store'])
    ->name('resources.allocate.store');
