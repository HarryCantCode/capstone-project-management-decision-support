<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_subtasks', function (Blueprint $table) {
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('completed_by')->constrained('users')->nullOnDelete();
        });

        // Backfill completed subtasks from staff_pending_actions
        try {
            $approvedActions = DB::table('staff_pending_actions')
                ->where('action_type', 'complete_subtask')
                ->where('status', 'approved')
                ->get();

            foreach ($approvedActions as $act) {
                $payload = json_decode($act->payload, true) ?: [];
                $subtaskId = $payload['subtask_id'] ?? null;
                if ($subtaskId) {
                    DB::table('project_subtasks')
                        ->where('id', $subtaskId)
                        ->whereNull('completed_by')
                        ->update([
                            'completed_by' => $act->user_id,
                            'approved_by' => $act->reviewed_by,
                        ]);
                }
            }

            // For completed subtasks still missing completed_by, backfill from project_files or project task
            $completedSubtasks = DB::table('project_subtasks')->where('is_completed', true)->whereNull('completed_by')->get();
            foreach ($completedSubtasks as $st) {
                $file = DB::table('project_files')->where('project_subtask_id', $st->id)->first();
                $userId = $file ? $file->uploaded_by : null;
                if (!$userId) {
                    $task = DB::table('project_tasks')->where('id', $st->project_task_id)->first();
                    $project = $task ? DB::table('projects')->where('id', $task->project_id)->first() : null;
                    $userId = $task?->created_by ?: ($project?->created_by ?: 1);
                }
                if ($userId) {
                    DB::table('project_subtasks')->where('id', $st->id)->update([
                        'completed_by' => $userId,
                        'approved_by' => $userId,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore backfill error on empty database
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_subtasks', function (Blueprint $table) {
            $table->dropForeign(['completed_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['completed_by', 'approved_by']);
        });
    }
};
