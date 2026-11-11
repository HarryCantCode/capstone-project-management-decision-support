<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectStatusHistory;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Services\NotificationService;
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

        $minStartDateRule = ['required', 'date'];
        if ($project->tasks()->exists()) {
            $latestTask = $project->tasks()->orderByDesc('end_date')->first();
            if ($latestTask && $latestTask->end_date) {
                $minAllowed = Carbon::parse($latestTask->end_date)->addDay()->toDateString();
                $minStartDateRule[] = 'after_or_equal:' . $minAllowed;
            }
        }

        $validated = $request->validate([
            'task_name' => ['required', 'string', 'max:255'],
            'start_date' => $minStartDateRule,
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
            'start_date.after_or_equal' => 'The start date must be on or after the day following the previous Major Task.',
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

        // Notify Admin and Manager users about the new major task (outside transaction for performance)
        app(NotificationService::class)->notifyNewTask($project, $task);

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

        // Notify Admin and Manager users about the new sub task
        $notificationService = app(NotificationService::class);
        $notificationService->notifyNewTask($project, $task, $subtask->title);

        if ($subtask->due_date && $subtask->due_date->isBetween(now()->startOfDay(), now()->addDays(2)->endOfDay())) {
            $notificationService->notifyUpcomingDueTask($project, $task, $subtask);
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
        abort_unless(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']), 403);
        abort_if($task->project_id !== $project->id || $subtask->project_task_id !== $task->id, 404);

        // Staff cannot uncheck already checked subtasks
        if (Auth::user()->hasRole('Staff') && $subtask->is_completed) {
            $msg = 'Staff users cannot uncheck completed tasks.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 403);
            }

            return back()->withErrors(['subtask' => $msg]);
        }

        $newCompleted = ! $subtask->is_completed;

        if ($newCompleted) {
            if ($request->has('remarks') && ! $request->has('notes')) {
                $request->merge(['notes' => $request->input('remarks')]);
            }

            $validated = $request->validate([
                'notes' => ['required', 'string', 'min:3', 'max:2000'],
                'proof_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:20480'],
                'proof_files' => ['nullable', 'array'],
                'proof_files.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:20480'],
            ], [
                'notes.required' => 'Completion remarks and notes are required before marking this task as done.',
                'notes.min' => 'Remarks and notes must be at least 3 characters.',
                'notes.max' => 'Remarks and notes may not exceed 2000 characters.',
                'proof_file.file' => 'The proof must be a valid uploaded file.',
                'proof_file.mimes' => 'The proof file must be a PDF, DOCX, or Image (JPG, JPEG, PNG, WEBP).',
                'proof_file.max' => 'The proof file may not exceed 20MB.',
                'proof_files.*.file' => 'Each proof file must be a valid uploaded file.',
                'proof_files.*.mimes' => 'Each proof file must be a PDF, DOCX, or Image (JPG, JPEG, PNG, WEBP).',
                'proof_files.*.max' => 'Each proof file may not exceed 20MB.',
            ]);

            $proofFilesList = [];
            if ($request->hasFile('proof_files')) {
                $proofFilesList = (array) $request->file('proof_files');
            } elseif ($request->hasFile('proof_file')) {
                $proofFilesList = [$request->file('proof_file')];
            }

            if (empty($proofFilesList)) {
                $msg = 'A proof file attachment is required before marking this task as done.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                        'errors' => ['proof_file' => [$msg]],
                    ], 422);
                }

                return back()->withErrors(['proof_file' => $msg]);
            }
        }

        $sortedTasks = $project->tasks()->with(['subtasks' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $currentTaskIndex = $sortedTasks->search(fn ($t) => $t->id === $task->id);
        $currentSubtasks = $task->subtasks()->orderBy('sort_order')->orderBy('id')->get();
        $currentSubtaskIndex = $currentSubtasks->search(fn ($st) => $st->id === $subtask->id);

        $isStaffUser = Auth::user()->hasRole('Staff');
        $pendingSubtaskIds = [];
        if ($isStaffUser) {
            $pendingSubtaskIds = \App\Models\StaffPendingAction::query()
                ->where('project_id', $project->id)
                ->where('action_type', 'complete_subtask')
                ->where('status', 'pending')
                ->get(['payload'])
                ->map(fn ($action) => (int) ($action->payload['subtask_id'] ?? 0))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $isSubtaskSatisfied = fn ($st): bool => (bool) $st->is_completed
            || ($isStaffUser && in_array((int) $st->id, $pendingSubtaskIds, true));

        // If ticking to complete: verify sequential prerequisites across tasks and subtasks
        if ($newCompleted) {
            // 1. All previous major tasks must have all their subtasks completed (or pending review for Staff)
            if ($currentTaskIndex > 0) {
                for ($i = 0; $i < $currentTaskIndex; $i++) {
                    $prevTask = $sortedTasks[$i];
                    $hasUncompletedInPrev = $prevTask->subtasks->contains(fn ($st) => ! $isSubtaskSatisfied($st));
                    if ($hasUncompletedInPrev) {
                        $msg = 'You cannot proceed to mark these tasks as in-progress, as you still have previous on-going task';
                        if ($request->wantsJson() || $request->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $msg,
                            ], 422);
                        }

                        return back()->withErrors(['subtask' => $msg]);
                    }
                }
            }

            // 2. In the current major task, all previous subtasks before this one must be completed (or pending review for Staff)
            if ($currentSubtaskIndex > 0) {
                for ($j = 0; $j < $currentSubtaskIndex; $j++) {
                    if (! $isSubtaskSatisfied($currentSubtasks[$j])) {
                        $msg = 'You cannot proceed to mark these tasks as in-progress, as you still have previous on-going task';
                        if ($request->wantsJson() || $request->ajax()) {
                            return response()->json([
                                'success' => false,
                                'message' => $msg,
                            ], 422);
                        }

                        return back()->withErrors(['subtask' => $msg]);
                    }
                }
            }
        } else {
            // If unticking: cannot uncheck a previous specific task if a subsequent specific task is still completed
            for ($j = $currentSubtaskIndex + 1; $j < $currentSubtasks->count(); $j++) {
                if ($currentSubtasks[$j]->is_completed) {
                    $msg = 'You cannot uncheck your previous task unless you uncheck the task that comes after it.';
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'message' => $msg,
                        ], 422);
                    }

                    return back()->withErrors(['subtask' => $msg]);
                }
            }

            for ($i = $currentTaskIndex + 1; $i < $sortedTasks->count(); $i++) {
                $nextTask = $sortedTasks[$i];
                if ($nextTask->subtasks->contains(fn ($st) => $st->is_completed)) {
                    $msg = 'You cannot uncheck your previous task unless you uncheck the task that comes after it.';
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'message' => $msg,
                        ], 422);
                    }

                    return back()->withErrors(['subtask' => $msg]);
                }
            }
        }

        // Staff action: requires review from Admin or Manager before reflecting
        if (Auth::user()->hasRole('Staff')) {
            $existingPending = \App\Models\StaffPendingAction::where('project_id', $project->id)
                ->where('action_type', 'complete_subtask')
                ->where('status', 'pending')
                ->whereJsonContains('payload->subtask_id', $subtask->id)
                ->first();

            if ($existingPending) {
                $msg = 'This task completion has already been submitted and is awaiting review by your manager or admin.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'already_pending' => true,
                        'message' => $msg,
                    ], 422);
                }

                return back()->withErrors(['subtask' => $msg]);
            }

            $storedFiles = [];
            foreach ($proofFilesList as $pf) {
                if ($pf && $pf->isValid()) {
                    $storedPath = $pf->store('project-files/' . $project->id, 'public');
                    $storedFiles[] = [
                        'file_name' => $pf->getClientOriginalName(),
                        'file_path' => $storedPath,
                        'file_size' => $pf->getSize(),
                        'mime_type' => $pf->getClientMimeType() ?: $pf->getMimeType(),
                    ];
                }
            }

            $primaryFile = $storedFiles[0] ?? null;
            $notesText = $validated['notes'] ?? (string) $request->input('notes');

            $pendingAction = \App\Models\StaffPendingAction::create([
                'user_id' => Auth::id(),
                'project_id' => $project->id,
                'action_type' => 'complete_subtask',
                'payload' => [
                    'task_id' => $task->id,
                    'task_name' => $task->task_name,
                    'subtask_id' => $subtask->id,
                    'subtask_name' => $subtask->title,
                    'subtask_title' => $subtask->title,
                    'notes' => $notesText,
                    'file_name' => $primaryFile['file_name'] ?? null,
                    'file_path' => $primaryFile['file_path'] ?? null,
                    'file_size' => $primaryFile['file_size'] ?? 0,
                    'mime_type' => $primaryFile['mime_type'] ?? null,
                    'files' => $storedFiles,
                    'file_type' => 'task_attachment',
                    'description' => $notesText,
                ],
                'status' => 'pending',
            ]);

            app(\App\Services\NotificationService::class)->notifyStaffActionPending($pendingAction);

            $pendingSubtaskIds[] = (int) $subtask->id;
            $subtaskLocks = [];
            $foundUncompleted = false;
            foreach ($sortedTasks as $tItem) {
                foreach ($tItem->subtasks as $sItem) {
                    $isPending = in_array((int) $sItem->id, $pendingSubtaskIds, true);
                    if ($isPending) {
                        $subtaskLocks[$sItem->id] = true;
                    } elseif (! $sItem->is_completed) {
                        if (! $foundUncompleted) {
                            $subtaskLocks[$sItem->id] = false;
                            $foundUncompleted = true;
                        } else {
                            $subtaskLocks[$sItem->id] = true;
                        }
                    } else {
                        $subtaskLocks[$sItem->id] = false;
                    }
                }
            }

            $msg = 'Your action has been submitted successfully and will be reviewed by your manager or admin to take effect.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'pending_approval' => true,
                    'pending_subtask_id' => $subtask->id,
                    'subtask_locks' => $subtaskLocks,
                    'message' => $msg,
                ]);
            }

            return back()->with('staff_submitted', $msg);
        }

        $uploadedProjectFiles = [];
        $notesText = $newCompleted ? ($validated['notes'] ?? (string) $request->input('notes')) : null;

        if ($newCompleted && !empty($proofFilesList)) {
            foreach ($proofFilesList as $pf) {
                if ($pf && $pf->isValid()) {
                    $upFile = $this->projectService->storeProjectFile(
                        $project,
                        $pf,
                        'task_attachment',
                        Auth::id(),
                        $notesText,
                        $subtask,
                    );
                    $uploadedProjectFiles[] = [
                        'id' => $upFile->id,
                        'name' => $upFile->file_name,
                        'size' => $upFile->formatted_size,
                        'url' => route('projects.files.download', [$project, $upFile]),
                        'is_img' => $upFile->is_image,
                        'is_pdf' => $upFile->is_pdf,
                        'is_word' => $upFile->is_word,
                    ];
                }
            }
        }
        $uploadedProjectFile = $uploadedProjectFiles[0] ?? null;

        $subtask->update([
            'is_completed' => $newCompleted,
            'completed_at' => $newCompleted ? now() : null,
            'completed_by' => $newCompleted ? Auth::id() : null,
            'approved_by' => $newCompleted ? Auth::id() : null,
            'notes' => $notesText,
        ]);

        $wasCompleted = $task->status === 'completed';

        // Sync major task status based on progress
        $task->load('subtasks');
        $newStatus = $task->computed_status;
        $task->update([
            'status' => $newStatus,
        ]);

        if (! $wasCompleted && $newStatus === 'completed') {
            app(NotificationService::class)->notifyMajorTaskCompleted($project, $task);
        }

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
            $tasksForLock = $project->tasks()->with(['subtasks' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->orderBy('sort_order')
                ->orderBy('start_date')
                ->orderBy('id')
                ->get();

            $orderedSubtasks = $tasksForLock->flatMap(fn ($t) => $t->subtasks)->values();
            $subtaskLockMap = [];
            $subtaskCompletedMap = [];
            $foundFirstUncompleted = false;
            $totalOrderedSubtasks = $orderedSubtasks->count();

            foreach ($orderedSubtasks as $idx => $st) {
                $subtaskCompletedMap[$st->id] = (bool) $st->is_completed;
                if ($st->is_completed) {
                    $hasCompletedAfter = false;
                    for ($k = $idx + 1; $k < $totalOrderedSubtasks; $k++) {
                        if ($orderedSubtasks[$k]->is_completed) {
                            $hasCompletedAfter = true;
                            break;
                        }
                    }
                    $subtaskLockMap[$st->id] = $hasCompletedAfter;
                } else {
                    if (! $foundFirstUncompleted) {
                        $subtaskLockMap[$st->id] = false;
                        $foundFirstUncompleted = true;
                    } else {
                        $subtaskLockMap[$st->id] = true;
                    }
                }
            }

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
                'subtask_locks' => $subtaskLockMap,
                'subtask_completed' => $subtaskCompletedMap,
                'notes' => $subtask->notes,
                'file' => $uploadedProjectFile ? [
                    'id' => $uploadedProjectFile->id,
                    'name' => $uploadedProjectFile->file_name,
                    'size' => $uploadedProjectFile->formatted_size,
                    'url' => route('projects.files.download', [$project, $uploadedProjectFile]),
                    'is_img' => $uploadedProjectFile->is_image,
                    'is_pdf' => $uploadedProjectFile->is_pdf,
                    'is_word' => $uploadedProjectFile->is_word,
                ] : null,
                'files' => $uploadedProjectFiles,
                'files_count' => count($uploadedProjectFiles),
                'proof_modal_data' => [
                    'subtask_id' => $subtask->id,
                    'task_name' => $task->task_name,
                    'subtask_title' => $subtask->title,
                    'is_completed' => (bool) $subtask->is_completed,
                    'completed_at' => $subtask->completed_at ? $subtask->completed_at->format('M j, Y · g:i A') : now()->format('M j, Y · g:i A'),
                    'due_date' => $subtask->due_date ? $subtask->due_date->format('M j, Y') : null,
                    'completed_by_name' => Auth::user()?->name ?? 'System',
                    'completed_by_role' => Auth::user()?->getRoleNames()->first() ?? 'Admin',
                    'approved_by_name' => Auth::user()?->name ?? 'System',
                    'approved_by_role' => Auth::user()?->getRoleNames()->first() ?? 'Admin',
                    'is_direct' => true,
                    'notes' => $subtask->notes ?? '',
                    'files' => $uploadedProjectFiles,
                ],
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

        if ($subtask->due_date && ! $subtask->is_completed && $subtask->due_date->isBetween(now()->startOfDay(), now()->addDays(2)->endOfDay())) {
            app(NotificationService::class)->notifyUpcomingDueTask($project, $task, $subtask);
        }

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
        $wasCompleted = $task->status === 'completed';
        $task->load('subtasks');
        $newStatus = $task->computed_status;
        $task->update([
            'status' => $newStatus,
        ]);

        if (! $wasCompleted && $newStatus === 'completed') {
            app(NotificationService::class)->notifyMajorTaskCompleted($project, $task);
        }

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
