<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project Management Routes
|--------------------------------------------------------------------------
| Loaded from routes/web.php once Phase 2 is active.
| All routes inside the 'auth + two-factor' middleware group.
*/

Route::resource('projects', ProjectController::class);

// Status transition — separate route from standard CRUD update
Route::patch('projects/{project}/transition', [ProjectController::class, 'transition'])
    ->name('projects.transition');

// Financial spend update
Route::patch('projects/{project}/spend', [ProjectController::class, 'updateSpend'])
    ->name('projects.spend');

// Manpower project assignment & unassignment directly from project view
Route::post('projects/{project}/personnel/{personnel}/unassign', [ProjectController::class, 'unassignPersonnel'])
    ->name('projects.unassign-personnel');

Route::post('projects/{project}/personnel/assign', [ProjectController::class, 'assignPersonnel'])
    ->name('projects.assign-personnel');

// Itemized Project Costing & Expenses
Route::post('projects/{project}/costs', [ProjectController::class, 'addCost'])
    ->name('projects.costs.store');

Route::delete('projects/{project}/costs/{cost}', [ProjectController::class, 'deleteCost'])
    ->name('projects.costs.destroy');

// Project Progress Timeline & Tasks (Major Tasks & Sub Tasks)
Route::post('projects/{project}/tasks', [\App\Http\Controllers\ProjectTaskController::class, 'store'])
    ->name('projects.tasks.store');

Route::put('projects/{project}/tasks/{task}', [\App\Http\Controllers\ProjectTaskController::class, 'update'])
    ->name('projects.tasks.update');

Route::delete('projects/{project}/tasks/{task}', [\App\Http\Controllers\ProjectTaskController::class, 'destroy'])
    ->name('projects.tasks.destroy');

Route::post('projects/{project}/tasks/{task}/subtasks', [\App\Http\Controllers\ProjectTaskController::class, 'addSubtask'])
    ->name('projects.tasks.subtasks.store');

Route::patch('projects/{project}/tasks/{task}/subtasks/{subtask}/toggle', [\App\Http\Controllers\ProjectTaskController::class, 'toggleSubtask'])
    ->name('projects.tasks.subtasks.toggle');

Route::put('projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskController::class, 'updateSubtask'])
    ->name('projects.tasks.subtasks.update');

Route::delete('projects/{project}/tasks/{task}/subtasks/{subtask}', [\App\Http\Controllers\ProjectTaskController::class, 'destroySubtask'])
    ->name('projects.tasks.subtasks.destroy');

Route::post('projects/{project}/tasks/initialize-defaults', [\App\Http\Controllers\ProjectTaskController::class, 'initializeDefaults'])
    ->name('projects.tasks.initialize-defaults');
