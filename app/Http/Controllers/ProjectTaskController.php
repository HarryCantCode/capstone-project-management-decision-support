<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ProjectTaskController — handles management of Major Tasks and Sub Tasks.
 *
 * All mutating actions are authorized against the project via $this->authorize('update', $project).
 */
class ProjectTaskController extends Controller
{
    /**
     * Store a new Major Task (and optional initial sub tasks).
     */
    public function store(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'task_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'subtasks' => ['nullable', 'array'],
            'subtasks.*' => ['nullable', 'string', 'max:255'],
            'subtask_dates' => ['nullable', 'array'],
            'subtask_dates.*' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            'baby_tasks' => ['nullable', 'array'],
            'baby_tasks.*' => ['nullable', 'string', 'max:255'],
            'baby_task_dates' => ['nullable', 'array'],
            'baby_task_dates.*' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
        ], [
            'subtask_dates.*.after_or_equal' => 'Sub task date must be on or after the Major Task start date.',
            'subtask_dates.*.before_or_equal' => 'Sub task date must be on or before the Major Task end date.',
            'baby_task_dates.*.after_or_equal' => 'Sub task date must be on or after the Major Task start date.',
            'baby_task_dates.*.before_or_equal' => 'Sub task date must be on or before the Major Task end date.',
        ]);

        $task = DB::transaction(function () use ($project, $validated, $request) {
            $nextOrder = (int) ($project->tasks()->max('sort_order') ?? 0) + 1;

            $task = $project->tasks()->create([
                'task_name' => $validated['task_name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'description' => $validated['description'] ?? null,
                'status' => 'pending',
                'sort_order' => $nextOrder,
                'created_by' => Auth::id(),
            ]);

            $subtasksInput = !empty($validated['subtasks']) ? $validated['subtasks'] : ($validated['baby_tasks'] ?? []);
            $datesInput = !empty($validated['subtask_dates']) ? $validated['subtask_dates'] : ($request->input('baby_task_dates', []));

            if (! empty($subtasksInput)) {
                $subtaskOrder = 1;
                foreach ($subtasksInput as $idx => $title) {
                    $trimmed = trim((string) $title);
                    if ($trimmed !== '') {
                        $dueDate = ! empty($datesInput[$idx]) ? $datesInput[$idx] : null;
                        $task->subtasks()->create([
                            'title' => $trimmed,
                            'due_date' => $dueDate,
                            'is_completed' => false,
                            'sort_order' => $subtaskOrder++,
                        ]);
                    }
                }
            }

            return $task;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Major task '{$task->task_name}' created successfully.",
                'task' => $task->load('subtasks'),
            ], 201);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Major task '{$task->task_name}' created successfully.");
    }

    /**
     * Update an existing Major Task's fields.
     */
    public function update(Request $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        abort_if($task->project_id !== $project->id, 404);

        $validated = $request->validate([
            'task_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $task->update([
            'task_name' => $validated['task_name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Major task '{$task->task_name}' updated successfully.",
                'task' => $task->fresh('subtasks'),
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Major task '{$task->task_name}' updated successfully.");
    }

    /**
     * Delete a Major Task and all its associated sub tasks.
     */
    public function destroy(Request $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        abort_if($task->project_id !== $project->id, 404);

        $taskName = $task->task_name;
        $task->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Major task '{$taskName}' removed successfully.",
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Major task '{$taskName}' removed successfully.");
    }

    /**
     * Add a sub task / specific checkbox item inside a Major Task.
     */
    public function addSubtask(Request $request, Project $project, ProjectTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        abort_if($task->project_id !== $project->id, 404);

        $startDateStr = $task->start_date->format('Y-m-d');
        $endDateStr = $task->end_date->format('Y-m-d');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => [
                'nullable',
                'date',
                'after_or_equal:' . $startDateStr,
                'before_or_equal:' . $endDateStr,
            ],
        ], [
            'due_date.after_or_equal' => "Sub task date must be within this Major Task's scheduled week ({$task->start_date->format('M j, Y')} to {$task->end_date->format('M j, Y')}).",
            'due_date.before_or_equal' => "Sub task date must be within this Major Task's scheduled week ({$task->start_date->format('M j, Y')} to {$task->end_date->format('M j, Y')}).",
        ]);

        $nextOrder = (int) ($task->subtasks()->max('sort_order') ?? 0) + 1;

        $subtask = $task->subtasks()->create([
            'title' => trim($validated['title']),
            'due_date' => $validated['due_date'] ?? null,
            'is_completed' => false,
            'sort_order' => $nextOrder,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Sub task added.",
                'subtask' => $subtask,
                'progress' => $task->fresh('subtasks')->progress_percentage,
                'status' => $task->fresh('subtasks')->computed_status,
            ], 201);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Sub task '{$subtask->title}' added successfully.");
    }

    /**
     * Toggle the completion checkbox of a sub task.
     */
    public function toggleSubtask(Request $request, Project $project, ProjectTask $task, ProjectSubtask $subtask): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        abort_if($task->project_id !== $project->id || $subtask->project_task_id !== $task->id, 404);

        $newCompleted = ! $subtask->is_completed;
        $subtask->update([
            'is_completed' => $newCompleted,
            'completed_at' => $newCompleted ? now() : null,
        ]);

        // Sync major task status based on progress
        $task->load('subtasks');
        $task->update([
            'status' => $task->computed_status,
        ]);

        $project->load('tasks.subtasks');
        $allTasks = $project->tasks;
        $totalSubtasksAcrossProject = $allTasks->sum(fn ($t) => $t->subtasks->count());
        $completedSubtasksAcrossProject = $allTasks->sum(fn ($t) => $t->subtasks->where('is_completed', true)->count());
        $overallProgress = $totalSubtasksAcrossProject > 0
            ? (int) round(($completedSubtasksAcrossProject / $totalSubtasksAcrossProject) * 100)
            : ($project->status === 'completed' ? 100 : 0);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_completed' => $subtask->is_completed,
                'progress' => $task->progress_percentage,
                'status' => $task->computed_status,
                'completed_count' => $task->subtasks->where('is_completed', true)->count(),
                'total_count' => $task->subtasks->count(),
                'overall_progress' => $overallProgress,
                'total_subtasks_across_project' => $totalSubtasksAcrossProject,
                'completed_subtasks_across_project' => $completedSubtasksAcrossProject,
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Task status updated.");
    }

    /**
     * Update a sub task title and optional date.
     */
    public function updateSubtask(Request $request, Project $project, ProjectTask $task, ProjectSubtask $subtask): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        abort_if($task->project_id !== $project->id || $subtask->project_task_id !== $task->id, 404);

        $startDateStr = $task->start_date->format('Y-m-d');
        $endDateStr = $task->end_date->format('Y-m-d');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => [
                'nullable',
                'date',
                'after_or_equal:' . $startDateStr,
                'before_or_equal:' . $endDateStr,
            ],
        ], [
            'due_date.after_or_equal' => "Sub task date must be within this Major Task's scheduled week ({$task->start_date->format('M j, Y')} to {$task->end_date->format('M j, Y')}).",
            'due_date.before_or_equal' => "Sub task date must be within this Major Task's scheduled week ({$task->start_date->format('M j, Y')} to {$task->end_date->format('M j, Y')}).",
        ]);

        $subtask->update([
            'title' => trim($validated['title']),
            'due_date' => $validated['due_date'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Sub task updated.",
                'subtask' => $subtask,
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Sub task updated.");
    }

    /**
     * Delete a sub task from a major task.
     */
    public function destroySubtask(Request $request, Project $project, ProjectTask $task, ProjectSubtask $subtask): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        abort_if($task->project_id !== $project->id || $subtask->project_task_id !== $task->id, 404);

        $subtask->delete();

        // Refresh task status
        $task->load('subtasks');
        $task->update([
            'status' => $task->computed_status,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Sub task removed.",
                'progress' => $task->progress_percentage,
                'status' => $task->computed_status,
                'completed_count' => $task->subtasks->where('is_completed', true)->count(),
                'total_count' => $task->subtasks->count(),
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Sub task removed.");
    }

    /**
     * Initialize default permit & mobilization major task for an existing project.
     */
    public function initializeDefaults(Project $project, ProjectService $projectService): RedirectResponse
    {
        $this->authorize('update', $project);

        $projectService->createDefaultPermitTask($project);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Initial default permit and mobilization task created successfully.");
    }
}
