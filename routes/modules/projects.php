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
