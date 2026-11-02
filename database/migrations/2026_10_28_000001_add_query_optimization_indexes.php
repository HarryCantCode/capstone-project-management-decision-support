<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration adding indexing to frequently sorted, filtered, and foreign key columns
 * for projects, project_tasks, and project_subtasks.
 *
 * Safe addition: Only adds database indexes, does not alter table structures or data.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Speed up listing with status filter & created_at order
            $table->index(['status', 'created_at'], 'projects_status_created_at_idx');
            $table->index('project_code', 'projects_project_code_idx');
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            // Foreign key + status lookup in dashboard and project show views
            $table->index(['project_id', 'status'], 'project_tasks_project_id_status_idx');
        });

        Schema::table('project_subtasks', function (Blueprint $table) {
            // Subtask completion lookup in dashboard progression
            $table->index(['project_task_id', 'is_completed'], 'project_subtasks_task_id_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_subtasks', function (Blueprint $table) {
            $table->dropIndex('project_subtasks_task_id_completed_idx');
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex('project_tasks_project_id_status_idx');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_status_created_at_idx');
            $table->dropIndex('projects_project_code_idx');
        });
    }
};
