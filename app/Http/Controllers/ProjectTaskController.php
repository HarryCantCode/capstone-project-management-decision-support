<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectStatusHistory;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Services\ProjectService;
use Carbon\Carbon;
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

            $startFormatted = Carbon::parse($task->start_date)->format('M j, Y');
            $endFormatted = Carbon::parse($task->end_date)->format('M j, Y');
            $majorTaskNote = "Created major task: '{$task->task_name}' ({$startFormatted} to {$endFormatted})";

            ProjectStatusHistory::create([
                'project_id'  => $project->id,
                'from_status' => $project->status,
                'to_status'   => $project->status,
                'notes'       => $majorTaskNote,
                'changed_by'  => Auth::id() ?? $project->created_by ?? 1,
                'changed_at'  => now(),
            ]);

            if (function_exists('activity')) {
                activity('project')
                    ->performedOn($project)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'task_id' => $task->id,
                        'task_name' => $task->task_name,
                        'start_date' => $task->start_date->toDateString(),
                        'end_date' => $task->end_date->toDateString(),
                    ])
                    ->log($majorTaskNote);
            }

            $subtasksInput = !empty($validated['subtasks']) ? $validated['subtasks'] : ($validated['baby_tasks'] ?? []);
            $datesInput = !empty($validated['subtask_dates']) ? $validated['subtask_dates'] : ($request->input('baby_task_dates', []));

            if (! empty($subtasksInput)) {
                $subtaskOrder = 1;
                foreach ($subtasksInput as $idx => $title) {
                    $trimmed = trim((string) $title);
                    if ($trimmed !== '') {
                        $dueDate = ! empty($datesInput[$idx]) ? $datesInput[$idx] : null;
                        $subtask = $task->subtasks()->create([
                            'title' => $trimmed,
                            'due_date' => $dueDate,
                            'is_completed' => false,
                            'sort_order' => $subtaskOrder++,
                        ]);

                        $dueDateNote = $dueDate ? ' (Due: ' . Carbon::parse($dueDate)->format('M j, Y') . ')' : '';
                        $minorTaskNote = "Created minor task: '{$subtask->title}'{$dueDateNote} under '{$task->task_name}'";

                        ProjectStatusHistory::create([
                            'project_id'  => $project->id,
                            'from_status' => $project->status,
                            'to_status'   => $project->status,
                            'notes'       => $minorTaskNote,
                            'changed_by'  => Auth::id() ?? $project->created_by ?? 1,
                            'changed_at'  => now(),
                        ]);

                        if (function_exists('activity')) {
                            activity('project')
                                ->performedOn($project)
                                ->causedBy(Auth::user())
                                ->withProperties([
                                    'task_id' => $task->id,
                                    'task_name' => $task->task_name,
                                    'subtask_id' => $subtask->id,
                                    'subtask_title' => $subtask->title,
                                    'due_date' => $dueDate,
                                ])
                                ->log($minorTaskNote);
                        }
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

        $dueDateNote = $subtask->due_date ? ' (Due: ' . $subtask->due_date->format('M j, Y') . ')' : '';
        $minorTaskNote = "Created minor task: '{$subtask->title}'{$dueDateNote} under '{$task->task_name}'";

        $history = ProjectStatusHistory::create([
            'project_id'  => $project->id,
            'from_status' => $project->status,
            'to_status'   => $project->status,
            'notes'       => $minorTaskNote,
            'changed_by'  => Auth::id() ?? $project->created_by ?? 1,
            'changed_at'  => now(),
        ]);

        if (function_exists('activity')) {
            activity('project')
                ->performedOn($project)
                ->causedBy(Auth::user())
                ->withProperties([
                    'task_id' => $task->id,
                    'task_name' => $task->task_name,
                    'subtask_id' => $subtask->id,
                    'subtask_title' => $subtask->title,
                    'due_date' => $subtask->due_date?->toDateString(),
                ])
                ->log($minorTaskNote);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Sub task added.",
                'subtask' => $subtask,
                'progress' => $task->fresh('subtasks')->progress_percentage,
                'status' => $task->fresh('subtasks')->computed_status,
                'audit_entry' => [
                    'date_time' => $history->changed_at->format('M j, Y · g:i A'),
                    'status' => $project->status,
                    'status_label' => in_array($project->status, ['ongoing', 'in_progress']) ? 'In Progress' : ucfirst($project->status),
                    'user_name' => Auth::user()?->name ?? 'System',
                    'notes' => $minorTaskNote,
                ],
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

        $statusTransitionedToOngoing = false;
        $statusTransitionedToPending = false;
        $statusTransitionedFromCompleted = false;
        $firstMajorTask = $project->tasks()->orderBy('sort_order')->orderBy('id')->first();

        // If ticking a subtask from the first major task, transition pending project to ongoing (in-progress)
        if ($newCompleted && $firstMajorTask && $task->id === $firstMajorTask->id && $project->status === 'pending') {
            $project->update([
                'status' => 'ongoing',
            ]);
            $statusTransitionedToOngoing = true;

            ProjectStatusHistory::create([
                'project_id'  => $project->id,
                'from_status' => 'pending',
                'to_status'   => 'ongoing',
                'notes'       => 'Status transitioned from Pending to Ongoing',
                'changed_by'  => Auth::id() ?? $project->created_by ?? 1,
                'changed_at'  => now(),
            ]);

            if (function_exists('activity')) {
                activity('project')
                    ->performedOn($project)
                    ->causedBy(Auth::user())
                    ->withProperties(['from_status' => 'pending', 'to_status' => 'ongoing'])
                    ->log('Status transitioned from Pending to Ongoing');
            }
        } elseif (! $newCompleted && $firstMajorTask && $task->id === $firstMajorTask->id && in_array($project->status, ['ongoing', 'in_progress'])) {
            // If unticking and no completed subtasks remain across the project, revert project to pending
            $hasAnyCompleted = $project->tasks()->whereHas('subtasks', fn ($q) => $q->where('is_completed', true))->exists();
            if (! $hasAnyCompleted) {
                $project->update([
                    'status' => 'pending',
                ]);
                $statusTransitionedToPending = true;

                ProjectStatusHistory::create([
                    'project_id'  => $project->id,
                    'from_status' => 'ongoing',
                    'to_status'   => 'pending',
                    'notes'       => 'Status transitioned from Ongoing to Pending',
                    'changed_by'  => Auth::id() ?? $project->created_by ?? 1,
                    'changed_at'  => now(),
                ]);

                if (function_exists('activity')) {
                    activity('project')
                        ->performedOn($project)
                        ->causedBy(Auth::user())
                        ->withProperties(['from_status' => 'ongoing', 'to_status' => 'pending'])
                        ->log('Status transitioned from Ongoing to Pending');
                }
            }
        } elseif (! $newCompleted && $project->status === 'completed') {
            // Reverse the state of a completed project back to ongoing / in progress when final task / checklist is unticked
            request()->merge(['notes' => 'Status reversed from Completed back to In Progress due to unchecked task']);
            $project->update([
                'status' => 'ongoing',
            ]);
            $statusTransitionedFromCompleted = true;

            if (function_exists('activity')) {
                activity('project')
                    ->performedOn($project)
                    ->causedBy(Auth::user())
                    ->withProperties(['from_status' => 'completed', 'to_status' => 'ongoing'])
                    ->log('Status reversed from Completed back to In Progress due to unchecked task');
            }
        }

        $project->load('tasks.subtasks');
        $allTasks = $project->tasks;
        $totalSubtasksAcrossProject = $allTasks->sum(fn ($t) => $t->subtasks->count());
        $completedSubtasksAcrossProject = $allTasks->sum(fn ($t) => $t->subtasks->where('is_completed', true)->count());
        $overallProgress = $totalSubtasksAcrossProject > 0
            ? (int) round(($completedSubtasksAcrossProject / $totalSubtasksAcrossProject) * 100)
            : ($project->status === 'completed' ? 100 : 0);

        // Check if all tasks/subtasks across the project are completed and project is not yet marked completed
        $promptCompletion = ($newCompleted && $totalSubtasksAcrossProject > 0 && $completedSubtasksAcrossProject === $totalSubtasksAcrossProject && $project->status !== 'completed');

        $projectStatus = $project->fresh()->status;
        $projectStatusLabel = in_array($projectStatus, ['ongoing', 'in_progress']) ? 'In Progress' : ucfirst($projectStatus);

        $actionNote = $newCompleted
            ? "Ticked checkbox: completed sub task '{$subtask->title}' under '{$task->task_name}'"
            : "Unticked checkbox: marked sub task '{$subtask->title}' as incomplete under '{$task->task_name}'";

        $history = ProjectStatusHistory::create([
            'project_id'  => $project->id,
            'from_status' => $projectStatus,
            'to_status'   => $projectStatus,
            'notes'       => $actionNote,
            'changed_by'  => Auth::id() ?? $project->created_by ?? 1,
            'changed_at'  => now(),
        ]);

        if (function_exists('activity')) {
            activity('project')
                ->performedOn($project)
                ->causedBy(Auth::user())
                ->withProperties([
                    'task_id' => $task->id,
                    'task_name' => $task->task_name,
                    'subtask_id' => $subtask->id,
                    'subtask_title' => $subtask->title,
                    'is_completed' => $newCompleted,
                ])
                ->log($actionNote);
        }

        if ($request->wantsJson() || $request->ajax()) {
            $responsePayload = [
                'success' => true,
                'is_completed' => $subtask->is_completed,
                'progress' => $task->progress_percentage,
                'status' => $task->computed_status,
                'completed_count' => $task->subtasks->where('is_completed', true)->count(),
                'total_count' => $task->subtasks->count(),
                'overall_progress' => $overallProgress,
                'total_subtasks_across_project' => $totalSubtasksAcrossProject,
                'completed_subtasks_across_project' => $completedSubtasksAcrossProject,
                'project_status' => $projectStatus,
                'project_status_label' => $projectStatusLabel,
                'prompt_completion' => $promptCompletion,
                'audit_entry' => [
                    'date_time' => $history->changed_at->format('M j, Y · g:i A'),
                    'status' => $projectStatus,
                    'status_label' => $projectStatusLabel,
                    'user_name' => Auth::user()?->name ?? 'System',
                    'notes' => $actionNote,
                ],
            ];

            if ($statusTransitionedToOngoing) {
                $responsePayload['transition_audit_entry'] = [
                    'date_time' => now()->format('M j, Y · g:i A'),
                    'status' => 'ongoing',
                    'status_label' => 'In Progress',
                    'user_name' => Auth::user()?->name ?? 'System',
                    'notes' => 'Status transitioned from Pending to Ongoing',
                ];
            } elseif ($statusTransitionedToPending) {
                $responsePayload['transition_audit_entry'] = [
                    'date_time' => now()->format('M j, Y · g:i A'),
                    'status' => 'pending',
                    'status_label' => 'Pending',
                    'user_name' => Auth::user()?->name ?? 'System',
                    'notes' => 'Status transitioned from Ongoing to Pending',
                ];
            } elseif ($statusTransitionedFromCompleted) {
                $responsePayload['transition_audit_entry'] = [
                    'date_time' => now()->format('M j, Y · g:i A'),
                    'status' => 'ongoing',
                    'status_label' => 'In Progress',
                    'user_name' => Auth::user()?->name ?? 'System',
                    'notes' => 'Status reversed from Completed back to In Progress due to unchecked task',
                ];
            }

            return response()->json($responsePayload);
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
