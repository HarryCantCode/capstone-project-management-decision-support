<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\ProjectFile;
use App\Models\ProjectStatusHistory;
use App\Models\ProjectSubtask;
use App\Models\StaffPendingAction;
use App\Services\NotificationService;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffPendingActionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
            'role:Admin|Manager',
        ];
    }

    public function __construct(
        private readonly ProjectService $projectService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Retrieve details for a specific pending staff action.
     */
    public function show(StaffPendingAction $pendingAction): JsonResponse
    {
        $pendingAction->load(['user', 'project', 'reviewer']);

        return response()->json([
            'success' => true,
            'action' => $pendingAction,
        ]);
    }

    /**
     * Approve and execute a pending staff action.
     */
    public function approve(Request $request, StaffPendingAction $pendingAction): JsonResponse
    {
        return DB::transaction(function () use ($pendingAction, $request) {
            $lockedAction = StaffPendingAction::with(['user', 'project'])
                ->lockForUpdate()
                ->findOrFail($pendingAction->id);

            if ($lockedAction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => "This action has already been {$lockedAction->status}.",
                ], 422);
            }

            $project = $lockedAction->project;
            if (!$project || $project->trashed() || $project->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot approve this action because the project is already completed or archived.',
                ], 422);
            }

            $payload = $lockedAction->payload ?? [];

            match ($lockedAction->action_type) {
                'add_expense' => $this->executeAddExpense($project, $lockedAction->user_id, $payload),
                'complete_subtask' => $this->executeCompleteSubtask($project, $lockedAction->user?->name ?? 'Staff', $payload),
                'add_file' => $this->executeAddFile($project, $lockedAction->user_id, $payload),
                default => null,
            };

            $lockedAction->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'admin_notes' => $request->input('notes'),
            ]);

            $this->syncReviewNotifications($lockedAction, 'approved');
            $this->notificationService->notifyStaffActionReviewed($lockedAction, 'approved');

            cache()->forget('dashboard.stats.global');

            return response()->json([
                'success' => true,
                'message' => 'Staff action accepted and applied to the system.',
                'project_url' => $project ? route('projects.show', $project->id) : null,
                'action' => $lockedAction->fresh(['user', 'project', 'reviewer']),
            ]);
        });
    }

    /**
     * Reject a pending staff action.
     */
    public function reject(Request $request, StaffPendingAction $pendingAction): JsonResponse
    {
        return DB::transaction(function () use ($pendingAction, $request) {
            $lockedAction = StaffPendingAction::with(['user', 'project'])
                ->lockForUpdate()
                ->findOrFail($pendingAction->id);

            if ($lockedAction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => "This action has already been {$lockedAction->status}.",
                ], 422);
            }

            if ($lockedAction->action_type === 'add_file' && !empty($lockedAction->payload['file_path'])) {
                if (Storage::disk('public')->exists($lockedAction->payload['file_path'])) {
                    Storage::disk('public')->delete($lockedAction->payload['file_path']);
                }
            }

            $lockedAction->update([
                'status' => 'rejected',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'admin_notes' => $request->input('notes'),
            ]);

            $this->syncReviewNotifications($lockedAction, 'rejected');
            $this->notificationService->notifyStaffActionReviewed($lockedAction, 'rejected');

            return response()->json([
                'success' => true,
                'message' => 'Staff action rejected.',
                'action' => $lockedAction->fresh(['user', 'project', 'reviewer']),
            ]);
        });
    }

    private function executeAddExpense($project, int $staffUserId, array $payload): void
    {
        $costData = [
            'description' => $payload['description'] ?? 'Expense',
            'cost_type' => $payload['cost_type'] ?? 'materials',
            'amount' => (float) ($payload['amount'] ?? 0),
            'quantity' => !empty($payload['quantity']) ? (int) $payload['quantity'] : null,
            'resource_id' => $payload['resource_id'] ?? null,
            'incurred_date' => $payload['incurred_date'] ?? now()->toDateString(),
            'created_by' => $staffUserId,
        ];

        $this->projectService->addCost($project, $costData);
    }

    private function executeCompleteSubtask($project, string $staffName, array $payload): void
    {
        $subtaskId = $payload['subtask_id'] ?? null;
        if (!$subtaskId) {
            return;
        }

        $subtask = ProjectSubtask::find($subtaskId);
        if (!$subtask || $subtask->is_completed) {
            return;
        }

        $task = $subtask->task;
        $wasCompleted = $task && $task->status === 'completed';

        $subtask->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        if ($task) {
            $task->load('subtasks');
            $newStatus = $task->computed_status;
            $task->update(['status' => $newStatus]);

            if (!$wasCompleted && $newStatus === 'completed') {
                $this->notificationService->notifyMajorTaskCompleted($project, $task);
            }

            $firstMajorTask = $project->tasks()->orderBy('sort_order')->orderBy('id')->first();
            if ($firstMajorTask && $task->id === $firstMajorTask->id && $project->status === 'pending') {
                $project->update(['status' => 'ongoing']);
            }

            $currentStatus = $project->fresh()->status;
            ProjectStatusHistory::create([
                'project_id'  => $project->id,
                'from_status' => $currentStatus,
                'to_status'   => $currentStatus,
                'notes'       => "Ticked checkbox: completed sub task '{$subtask->title}' under '{$task->task_name}' (Submitted by {$staffName})",
                'changed_by'  => Auth::id() ?? $project->created_by ?? 1,
                'changed_at'  => now(),
            ]);
        }
    }

    private function executeAddFile($project, int $userId, array $payload): void
    {
        $projectFile = ProjectFile::create([
            'project_id' => $project->id,
            'project_subtask_id' => $payload['subtask_id'] ?? null,
            'uploaded_by' => $userId,
            'file_name' => $payload['file_name'] ?? 'File',
            'file_path' => $payload['file_path'],
            'file_type' => $payload['file_type'] ?? 'general',
            'mime_type' => $payload['mime_type'] ?? 'application/octet-stream',
            'file_size' => $payload['file_size'] ?? 0,
            'description' => $payload['description'] ?? null,
        ]);

        $this->notificationService->notifyFileUpload($project, $projectFile);
    }

    /**
     * Mark all Admin/Manager review notifications for this pending action as read
     * and update their embedded status payload so no reviewer sees a stale prompt.
     */
    private function syncReviewNotifications(StaffPendingAction $pendingAction, string $decision): void
    {
        $notifications = Notification::where('type', 'staff_action_review')
            ->whereJsonContains('data->pending_action_id', $pendingAction->id)
            ->get();

        $reviewerName = Auth::user()?->name ?? 'Reviewer';
        $now = now();

        foreach ($notifications as $notification) {
            $data = $notification->data ?? [];
            $data['status'] = $decision;
            $data['reviewed_by_name'] = $reviewerName;
            $data['reviewed_at'] = $now->format('M j, Y · g:i A');

            $notification->update([
                'read_at' => $notification->read_at ?? $now,
                'data' => $data,
            ]);
        }
    }
}
