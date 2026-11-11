<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\ProjectFile;
use App\Models\ProjectStatusHistory;
use App\Models\ProjectSubtask;
use App\Models\StaffPendingAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * StaffPendingActionService — handles querying, filtering, staff aggregation,
 * and transactional approval/rejection of StaffPendingAction records.
 */
class StaffPendingActionService
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Build the dataset for the Staff Request Approvals module (both Request List and Staff List views).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getModuleData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $rawStatus = strtolower(trim((string) ($filters['status'] ?? '')));
        $recency = strtolower(trim((string) ($filters['recency'] ?? 'newest')));
        $actionType = strtolower(trim((string) ($filters['action_type'] ?? '')));
        $view = strtolower(trim((string) ($filters['view'] ?? 'list')));

        if (!in_array($view, ['list', 'staff'], true)) {
            $view = 'list';
        }

        if (!in_array($recency, ['newest', 'oldest', 'today', '7days', '30days'], true)) {
            $recency = 'newest';
        }

        if (!in_array($actionType, ['complete_subtask', 'add_expense', 'add_file'], true)) {
            $actionType = '';
        }

        $dbStatus = match ($rawStatus) {
            'unexamined', 'pending' => 'pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => '',
        };

        $statusFilter = $dbStatus === 'pending' ? 'unexamined' : $dbStatus;

        // Global KPI counts
        $totalCount = StaffPendingAction::count();
        $unexaminedCount = StaffPendingAction::where('status', 'pending')->count();
        $approvedCount = StaffPendingAction::where('status', 'approved')->count();
        $rejectedCount = StaffPendingAction::where('status', 'rejected')->count();

        // 1. Filtered Request Approvals List query (Eager-loaded to prevent N+1)
        $actionsQuery = StaffPendingAction::query()
            ->with(['user', 'project', 'reviewer']);

        $this->applyActionSearch($actionsQuery, $search);

        if ($dbStatus !== '') {
            $actionsQuery->where('status', $dbStatus);
        }

        if ($actionType !== '') {
            $actionsQuery->where('action_type', $actionType);
        }

        match ($recency) {
            'today' => $actionsQuery->whereDate('created_at', now()->toDateString())->orderByDesc('created_at')->orderByDesc('id'),
            '7days' => $actionsQuery->where('created_at', '>=', now()->subDays(7)->startOfDay())->orderByDesc('created_at')->orderByDesc('id'),
            '30days' => $actionsQuery->where('created_at', '>=', now()->subDays(30)->startOfDay())->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $actionsQuery->orderBy('created_at')->orderBy('id'),
            default => $actionsQuery->orderByDesc('created_at')->orderByDesc('id'),
        };

        $actions = $actionsQuery->paginate(20)->withQueryString();

        // 2. Staff List with request counts and eager-loaded requests for the Staff Modal
        $staffQuery = User::query()
            ->where(function ($q) {
                $q->role('Staff')
                    ->orWhereHas('pendingActions');
            })
            ->withCount([
                'pendingActions as total_requests_count',
                'pendingActions as unexamined_requests_count' => fn ($q) => $q->where('status', 'pending'),
                'pendingActions as approved_requests_count' => fn ($q) => $q->where('status', 'approved'),
                'pendingActions as rejected_requests_count' => fn ($q) => $q->where('status', 'rejected'),
            ])
            ->with([
                'pendingActions' => fn ($q) => $q->with(['project', 'reviewer'])->orderByDesc('created_at')->orderByDesc('id'),
            ]);

        if ($search !== '') {
            $staffQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%")
                    ->orWhereHas('pendingActions', function ($actionQ) use ($search) {
                        $this->applyActionSearch($actionQ, $search);
                    });
            });
        }

        $staffMembers = $staffQuery
            ->orderByDesc('unexamined_requests_count')
            ->orderByDesc('total_requests_count')
            ->orderBy('name')
            ->get();

        // Prepare structured JSON-ready maps for Alpine modals
        $actionsMap = [];
        foreach ($actions as $action) {
            $actionsMap[$action->id] = $this->formatActionForModal($action);
        }

        $staffRequestsMap = [];
        foreach ($staffMembers as $staff) {
            $formattedActions = $staff->pendingActions->map(function (StaffPendingAction $act) use ($staff, &$actionsMap) {
                $act->setRelation('user', $staff);
                $formatted = $this->formatActionForModal($act);
                $actionsMap[$act->id] = $formatted;

                return $formatted;
            })->values()->all();

            $latestAction = $staff->pendingActions->first();

            $staffRequestsMap[$staff->id] = [
                'id' => $staff->id,
                'name' => $staff->name,
                'initials' => strtoupper(substr($staff->first_name ?? $staff->name, 0, 1) . substr($staff->last_name ?? '', 0, 1)),
                'email' => $staff->email,
                'account_number' => $staff->account_number ?? 'N/A',
                'employee_number' => $staff->employee_number ?? 'N/A',
                'job_title' => $staff->job_title ?: 'Staff Member',
                'total_count' => (int) $staff->total_requests_count,
                'unexamined_count' => (int) $staff->unexamined_requests_count,
                'approved_count' => (int) $staff->approved_requests_count,
                'rejected_count' => (int) $staff->rejected_requests_count,
                'latest_request_at' => $latestAction?->created_at?->format('M j, Y · g:i A'),
                'latest_request_diff' => $latestAction?->created_at?->diffForHumans(),
                'requests' => $formattedActions,
            ];
        }

        return [
            'actions' => $actions,
            'staffMembers' => $staffMembers,
            'actionsMap' => $actionsMap,
            'staffRequestsMap' => $staffRequestsMap,
            'search' => $search,
            'status' => $statusFilter,
            'recency' => $recency,
            'actionType' => $actionType,
            'view' => $view,
            'totalCount' => $totalCount,
            'unexaminedCount' => $unexaminedCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
        ];
    }

    /**
     * Build dataset for the staff member's own Approval List module.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getStaffApprovalListData(User $user, array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $rawStatus = strtolower(trim((string) ($filters['status'] ?? '')));
        $recency = strtolower(trim((string) ($filters['recency'] ?? 'newest')));
        $actionType = strtolower(trim((string) ($filters['action_type'] ?? '')));

        if (!in_array($recency, ['newest', 'oldest', 'today', '7days', '30days'], true)) {
            $recency = 'newest';
        }

        if (!in_array($actionType, ['complete_subtask', 'add_expense', 'add_file'], true)) {
            $actionType = '';
        }

        $dbStatus = match ($rawStatus) {
            'unexamined', 'pending' => 'pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            default => '',
        };

        $statusFilter = $dbStatus === 'pending' ? 'unexamined' : $dbStatus;

        // User scoped KPI counts
        $userScope = StaffPendingAction::where('user_id', $user->id);
        $totalCount = (clone $userScope)->count();
        $unexaminedCount = (clone $userScope)->where('status', 'pending')->count();
        $approvedCount = (clone $userScope)->where('status', 'approved')->count();
        $rejectedCount = (clone $userScope)->where('status', 'rejected')->count();

        // Filtered Query
        $actionsQuery = StaffPendingAction::query()
            ->where('user_id', $user->id)
            ->with(['project', 'reviewer']);

        $this->applyActionSearch($actionsQuery, $search);

        if ($dbStatus !== '') {
            $actionsQuery->where('status', $dbStatus);
        }

        if ($actionType !== '') {
            $actionsQuery->where('action_type', $actionType);
        }

        match ($recency) {
            'today' => $actionsQuery->whereDate('created_at', now()->toDateString())->orderByDesc('created_at')->orderByDesc('id'),
            '7days' => $actionsQuery->where('created_at', '>=', now()->subDays(7)->startOfDay())->orderByDesc('created_at')->orderByDesc('id'),
            '30days' => $actionsQuery->where('created_at', '>=', now()->subDays(30)->startOfDay())->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $actionsQuery->orderBy('created_at')->orderBy('id'),
            default => $actionsQuery->orderByDesc('created_at')->orderByDesc('id'),
        };

        $actions = $actionsQuery->paginate(20)->withQueryString();

        $actionsMap = [];
        $cooldownsMap = [];
        foreach ($actions as $action) {
            $action->setRelation('user', $user);
            $formatted = $this->formatActionForModal($action);
            $actionsMap[$action->id] = $formatted;
            $cooldownsMap[$action->id] = $action->cooldown_seconds_remaining;
        }

        return [
            'actions' => $actions,
            'actionsMap' => $actionsMap,
            'cooldownsMap' => $cooldownsMap,
            'search' => $search,
            'status' => $statusFilter,
            'recency' => $recency,
            'actionType' => $actionType,
            'totalCount' => $totalCount,
            'unexaminedCount' => $unexaminedCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
        ];
    }

    /**
     * Ping / send a reminder for a pending action with a 15-minute cooldown.
     *
     * @return array<string, mixed>
     */
    public function ping(StaffPendingAction $action, User $staffUser): array
    {
        if ((int) $action->user_id !== (int) $staffUser->id) {
            return [
                'success' => false,
                'status_code' => 403,
                'message' => 'Unauthorized. You can only ping your own submitted requests.',
            ];
        }

        if ($action->status !== 'pending') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => "Cannot ping this request because it has already been {$action->status}.",
            ];
        }

        if (!$action->isPingable()) {
            $remaining = $action->cooldown_seconds_remaining;
            $mins = ceil($remaining / 60);

            return [
                'success' => false,
                'status_code' => 429,
                'message' => "Please wait {$mins} minute(s) before sending another reminder.",
                'cooldown_seconds' => $remaining,
            ];
        }

        $action->update([
            'last_pinged_at' => now(),
        ]);

        $this->notificationService->notifyStaffActionPing($action);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Reminder sent to Admin and Manager. Cooldown timer started (15m).',
            'cooldown_seconds' => 900,
            'last_pinged_at' => $action->fresh()->last_pinged_at?->format('M j, Y · g:i A'),
        ];
    }

    /**
     * Format a single StaffPendingAction model for client-side modals.
     *
     * @return array<string, mixed>
     */
    public function formatActionForModal(StaffPendingAction $action): array
    {
        $payload = $action->payload ?? [];
        $fileExists = !empty($payload['file_path']) && $action->status !== 'rejected' && Storage::disk('public')->exists($payload['file_path']);

        $filesList = [];
        if (!empty($payload['files']) && is_array($payload['files'])) {
            foreach ($payload['files'] as $fItem) {
                $exists = !empty($fItem['file_path']) && $action->status !== 'rejected' && Storage::disk('public')->exists($fItem['file_path']);
                $filesList[] = [
                    'name' => $fItem['file_name'] ?? 'Proof File',
                    'path' => $fItem['file_path'] ?? '',
                    'size' => !empty($fItem['file_size']) ? number_format($fItem['file_size'] / 1048576, 2) . ' MB' : '0 MB',
                    'exists' => $exists,
                    'url' => $exists ? asset('storage/' . $fItem['file_path']) : null,
                ];
            }
        } elseif ($fileExists) {
            $filesList[] = [
                'name' => $payload['file_name'] ?? 'Proof File',
                'path' => $payload['file_path'],
                'size' => !empty($payload['file_size']) ? number_format($payload['file_size'] / 1048576, 2) . ' MB' : '0 MB',
                'exists' => true,
                'url' => asset('storage/' . $payload['file_path']),
            ];
        }

        return [
            'id' => $action->id,
            'action_type' => $action->action_type,
            'action_type_label' => $action->action_type_label,
            'summary_title' => $action->summary_title,
            'summary_subtitle' => $action->summary_subtitle,
            'status' => $action->status,
            'status_label' => $action->status_label,
            'staff_id' => $action->user_id,
            'staff_name' => $action->user?->name ?? 'Staff',
            'staff_email' => $action->user?->email ?? '',
            'staff_account_number' => $action->user?->account_number ?? 'N/A',
            'project_id' => $action->project_id,
            'project_name' => $action->project?->name ?? 'Project',
            'project_code' => $action->project?->project_code ?? 'N/A',
            'project_url' => $action->project_id ? route('projects.show', $action->project_id) : '#',
            'payload' => $payload,
            'file_exists' => $fileExists,
            'file_url' => $fileExists ? asset('storage/' . $payload['file_path']) : null,
            'files' => $filesList,
            'submitted_at' => $action->created_at?->format('M j, Y · g:i A') ?? 'N/A',
            'submitted_diff' => $action->created_at?->diffForHumans() ?? '',
            'submitted_timestamp' => $action->created_at?->timestamp ?? 0,
            'reviewed_by_name' => $action->reviewer?->name,
            'reviewed_at' => $action->reviewed_at?->format('M j, Y · g:i A'),
            'admin_notes' => $action->admin_notes,
            'last_pinged_at' => $action->last_pinged_at?->format('M j, Y · g:i A'),
            'last_pinged_diff' => $action->last_pinged_at?->diffForHumans(),
            'cooldown_seconds_remaining' => $action->cooldown_seconds_remaining,
            'is_pingable' => $action->isPingable(),
            'ping_url' => \Illuminate\Support\Facades\Route::has('approval-list.ping') ? route('approval-list.ping', $action->id) : url('/approval-list/' . $action->id . '/ping'),
            'approve_url' => \Illuminate\Support\Facades\Route::has('pending-actions.approve') ? route('pending-actions.approve', $action->id) : url('/pending-actions/' . $action->id . '/approve'),
            'reject_url' => \Illuminate\Support\Facades\Route::has('pending-actions.reject') ? route('pending-actions.reject', $action->id) : url('/pending-actions/' . $action->id . '/reject'),
        ];
    }

    /**
     * Approve and execute a pending staff action atomically.
     *
     * @return array<string, mixed>
     */
    public function approve(StaffPendingAction $pendingAction, int $reviewerId, ?string $notes = null): array
    {
        return DB::transaction(function () use ($pendingAction, $reviewerId, $notes) {
            $lockedAction = StaffPendingAction::with(['user', 'project'])
                ->lockForUpdate()
                ->findOrFail($pendingAction->id);

            if ($lockedAction->status !== 'pending') {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => "This action has already been {$lockedAction->status}.",
                ];
            }

            $project = $lockedAction->project;
            if (!$project || $project->trashed() || $project->status === 'completed') {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => 'Cannot approve this action because the project is already completed or archived.',
                ];
            }

            $payload = $lockedAction->payload ?? [];

            match ($lockedAction->action_type) {
                'add_expense' => $this->executeAddExpense($project, $lockedAction->user_id, $payload),
                'complete_subtask' => $this->executeCompleteSubtask($project, $lockedAction->user?->name ?? 'Staff', $reviewerId, $payload, $lockedAction->user_id),
                'add_file' => $this->executeAddFile($project, $lockedAction->user_id, $payload),
                default => null,
            };

            $lockedAction->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'admin_notes' => $notes,
            ]);

            $this->syncReviewNotifications($lockedAction, 'approved');
            $this->notificationService->notifyStaffActionReviewed($lockedAction, 'approved');

            cache()->forget('dashboard.stats.global');

            $freshAction = $lockedAction->fresh(['user', 'project', 'reviewer']);

            return [
                'success' => true,
                'status_code' => 200,
                'message' => 'Staff request approved and applied to the project records.',
                'project_url' => $project ? route('projects.show', $project->id) : null,
                'action' => $freshAction,
                'formatted_action' => $this->formatActionForModal($freshAction),
            ];
        });
    }

    /**
     * Reject a pending staff action atomically.
     *
     * @return array<string, mixed>
     */
    public function reject(StaffPendingAction $pendingAction, int $reviewerId, ?string $notes = null): array
    {
        return DB::transaction(function () use ($pendingAction, $reviewerId, $notes) {
            $lockedAction = StaffPendingAction::with(['user', 'project'])
                ->lockForUpdate()
                ->findOrFail($pendingAction->id);

            if ($lockedAction->status !== 'pending') {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => "This action has already been {$lockedAction->status}.",
                ];
            }

            if (in_array($lockedAction->action_type, ['add_file', 'complete_subtask'], true)) {
                if (!empty($lockedAction->payload['files']) && is_array($lockedAction->payload['files'])) {
                    foreach ($lockedAction->payload['files'] as $fItem) {
                        if (!empty($fItem['file_path']) && Storage::disk('public')->exists($fItem['file_path'])) {
                            Storage::disk('public')->delete($fItem['file_path']);
                        }
                    }
                } elseif (!empty($lockedAction->payload['file_path'])) {
                    if (Storage::disk('public')->exists($lockedAction->payload['file_path'])) {
                        Storage::disk('public')->delete($lockedAction->payload['file_path']);
                    }
                }
            }

            $lockedAction->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'admin_notes' => $notes,
            ]);

            $this->syncReviewNotifications($lockedAction, 'rejected');
            $this->notificationService->notifyStaffActionReviewed($lockedAction, 'rejected');

            $freshAction = $lockedAction->fresh(['user', 'project', 'reviewer']);

            return [
                'success' => true,
                'status_code' => 200,
                'message' => 'Staff request rejected.',
                'action' => $freshAction,
                'formatted_action' => $this->formatActionForModal($freshAction),
            ];
        });
    }

    /**
     * Apply multi-field search across StaffPendingAction queries.
     */
    private function applyActionSearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $lowerSearch = strtolower($search);
        $matchedTypes = [];
        if (str_contains('task completion timeline subtask check', $lowerSearch) || str_contains($lowerSearch, 'task')) {
            $matchedTypes[] = 'complete_subtask';
        }
        if (str_contains('expense entry cost material spend', $lowerSearch) || str_contains($lowerSearch, 'expense') || str_contains($lowerSearch, 'cost')) {
            $matchedTypes[] = 'add_expense';
        }
        if (str_contains('file upload attachment invoice document', $lowerSearch) || str_contains($lowerSearch, 'file') || str_contains($lowerSearch, 'upload')) {
            $matchedTypes[] = 'add_file';
        }

        $query->where(function ($q) use ($search, $matchedTypes) {
            $q->where('action_type', 'like', "%{$search}%")
                ->orWhere('admin_notes', 'like', "%{$search}%")
                ->orWhere('payload->subtask_title', 'like', "%{$search}%")
                ->orWhere('payload->task_name', 'like', "%{$search}%")
                ->orWhere('payload->description', 'like', "%{$search}%")
                ->orWhere('payload->cost_type', 'like', "%{$search}%")
                ->orWhere('payload->resource_name', 'like', "%{$search}%")
                ->orWhere('payload->file_name', 'like', "%{$search}%")
                ->orWhere('payload->amount', 'like', "%{$search}%")
                ->orWhereHas('user', function ($userQ) use ($search) {
                    $userQ->withTrashed()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                })
                ->orWhereHas('project', function ($projQ) use ($search) {
                    $projQ->withTrashed()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('project_code', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%");
                })
                ->orWhereHas('reviewer', function ($revQ) use ($search) {
                    $revQ->withTrashed()
                        ->where('name', 'like', "%{$search}%");
                });

            if (!empty($matchedTypes)) {
                $q->orWhereIn('action_type', $matchedTypes);
            }
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

    private function executeCompleteSubtask($project, string $staffName, int $reviewerId, array $payload, ?int $staffUserId = null): void
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

        $notes = $payload['notes'] ?? null;

        $subtask->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $staffUserId ?: ($project->created_by ?? 1),
            'approved_by' => $reviewerId ?: ($project->created_by ?? 1),
            'notes' => $notes,
        ]);

        $filesToCreate = [];
        if (!empty($payload['files']) && is_array($payload['files'])) {
            $filesToCreate = $payload['files'];
        } elseif (!empty($payload['file_path'])) {
            $filesToCreate[] = [
                'file_name' => $payload['file_name'] ?? 'Proof File',
                'file_path' => $payload['file_path'],
                'mime_type' => $payload['mime_type'] ?? 'application/octet-stream',
                'file_size' => $payload['file_size'] ?? 0,
            ];
        }

        foreach ($filesToCreate as $fData) {
            if (!empty($fData['file_path'])) {
                $projectFile = ProjectFile::create([
                    'project_id' => $project->id,
                    'project_subtask_id' => $subtask->id,
                    'uploaded_by' => $reviewerId ?: ($project->created_by ?? 1),
                    'file_name' => $fData['file_name'] ?? 'Proof File',
                    'file_path' => $fData['file_path'],
                    'file_type' => 'task_attachment',
                    'mime_type' => $fData['mime_type'] ?? 'application/octet-stream',
                    'file_size' => $fData['file_size'] ?? 0,
                    'description' => $notes ?? "Proof of completion for '{$subtask->title}'",
                ]);

                $this->notificationService->notifyFileUpload($project, $projectFile);
            }
        }

        if ($task) {
            $task->load('subtasks');
            $newStatus = $task->computed_status;
            $task->update(['status' => $newStatus]);

            if (!$wasCompleted && $newStatus === 'completed') {
                $this->notificationService->notifyMajorTaskCompleted($project, $task);
            }

            if ($project->status === 'pending') {
                $project->update(['status' => 'ongoing']);
                ProjectStatusHistory::create([
                    'project_id'  => $project->id,
                    'from_status' => 'pending',
                    'to_status'   => 'ongoing',
                    'notes'       => 'Status transitioned from Pending to Ongoing',
                    'changed_by'  => $reviewerId ?: ($project->created_by ?? 1),
                    'changed_at'  => now(),
                ]);
            }

            $currentStatus = $project->fresh()->status;
            ProjectStatusHistory::create([
                'project_id'  => $project->id,
                'from_status' => $currentStatus,
                'to_status'   => $currentStatus,
                'notes'       => "Ticked checkbox: completed sub task '{$subtask->title}' under '{$task->task_name}' (Submitted by {$staffName})",
                'changed_by'  => $reviewerId ?: ($project->created_by ?? 1),
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

        $reviewerName = User::find($pendingAction->reviewed_by)?->name ?? 'Reviewer';
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
