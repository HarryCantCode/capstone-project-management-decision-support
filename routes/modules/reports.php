<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/projects/{project}/pdf', [ReportController::class, 'exportProjectPdf'])->name('project.pdf');
    Route::post('/projects/portfolio/export', [ReportController::class, 'exportPortfolio'])->name('portfolio.export');
    Route::post('/revenue/export', [ReportController::class, 'exportRevenue'])->name('revenue.export');
    Route::post('/materials/export', [ReportController::class, 'exportMaterials'])->name('materials.export');
    Route::post('/manpower/export', [ReportController::class, 'exportManpower'])->name('manpower.export');
});
