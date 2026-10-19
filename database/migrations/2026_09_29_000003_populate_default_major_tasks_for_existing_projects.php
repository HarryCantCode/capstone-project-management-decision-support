<?php

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     * Backfill the default Pre-construction & Regulatory Compliance major task
     * and 4 subtasks for any existing projects that do not yet have timeline tasks.
     */
    public function up(): void
    {
        $projectService = app(ProjectService::class);
        $projects = Project::whereDoesntHave('tasks')->get();

        foreach ($projects as $project) {
            $projectService->createDefaultPermitTask($project, $project->created_by);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive rollback
    }
};
