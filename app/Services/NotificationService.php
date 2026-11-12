<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectFile;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
use App\Models\StaffPendingAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * NotificationService — creates and dispatches in-app notifications.
 *
 * Notifications are targeted at users with Admin or Manager roles.
 * This service is the single entry point for all notification creation;
 * controllers and commands call these methods rather than creating
 * Notification records directly.
 */
class NotificationService
{
    private static array $lowStockDispatchedInRequest = [];

    public function __construct(
        private readonly ?EmailNotificationService $emailNotificationService = null,
    ) {
    }

    private function getEmailService(): EmailNotificationService
    {
        return $this->emailNotificationService ?? app(EmailNotificationService::class);
    }
    /**
     * Notify Admin, Manager, and Staff users about a newly created project.
     */
    public function notifyNewProject(Project $project): void
    {
        $recipients = $this->getAllOperationalUsers();
        $projectCode = $project->project_code ?? 'N/A';
        $clientName = $project->client_name ?? 'Unknown';

        $title = 'New Project Created';
        $message = "Project \"{$project->name}\" ({$projectCode}) for client {$clientName} has been created and is now pending.";

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'client_name' => $clientName,
        ];

        $this->createForRecipients($recipients, 'new_project', $title, $message, $data);
        $this->getEmailService()->sendProjectCreatedEmail($project);
    }

    /**
     * Notify Admin, Manager, and Staff users when a project changes status to In-Progress (ongoing) or Completed.
     */
    public function notifyProjectStatusChanged(Project $project, string $fromStatus, string $toStatus): void
    {
        $recipients = $this->getAllOperationalUsers();
        $projectCode = $project->project_code ?? 'N/A';

        $formatStatus = static function (string $status): string {
            return match (strtolower($status)) {
                'ongoing', 'in_progress', 'in-progress' => 'In-Progress',
                'completed' => 'Completed',
                'delayed' => 'Delayed',
                'pending' => 'Pending',
                default => ucfirst($status),
            };
        };

        $fromLabel = $formatStatus($fromStatus);
        $toLabel = $formatStatus($toStatus);

        $title = match (strtolower($toStatus)) {
            'ongoing', 'in_progress', 'in-progress' => 'Project Status: In-Progress',
            'completed' => 'Project Status: Completed',
            default => "Project Status: {$toLabel}",
        };

        $message = "Project \"{$project->name}\" ({$projectCode}) status changed from {$fromLabel} to {$toLabel}.";

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'url' => route('projects.show', $project->id),
        ];

        $this->createForRecipients($recipients, 'project_status_changed', $title, $message, $data);

        // Action 5: Project Completed email notification
        if (strtolower($toStatus) === 'completed') {
            $this->getEmailService()->sendProjectCompletedEmail($project);
        }
    }

    /**
     * Notify about project completion directly.
     */
    public function notifyProjectCompleted(Project $project): void
    {
        $fromStatus = (string) ($project->getOriginal('status') ?? 'ongoing');
        $this->notifyProjectStatusChanged($project, $fromStatus, 'completed');
    }

    /**
     * Notify Admin and Manager users when a project is archived.
     */
    public function notifyProjectArchived(Project $project): void
    {
        $recipients = $this->getAdminAndManagerUsers();
        $projectCode = $project->project_code ?? 'N/A';

        $title = 'Project Archived';
        $message = "Project \"{$project->name}\" ({$projectCode}) has been archived.";

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'status' => $project->status,
            'url' => route('projects.index', ['archived' => 1]),
        ];

        $this->createForRecipients($recipients, 'project_archived', $title, $message, $data);
    }

    /**
     * Notify Admin, Manager, and Staff users about a new major task or specific sub task logged on a project.
     */
    public function notifyNewTask(Project $project, ProjectTask $task, ?string $subtaskTitle = null, ?ProjectSubtask $subtask = null): void
    {
        $recipients = $this->getAllOperationalUsers();
        $projectCode = $project->project_code ?? 'N/A';

        if ($subtaskTitle !== null) {
            $title = 'New Sub Task Added';
            $message = "A new sub task \"{$subtaskTitle}\" was added under major task \"{$task->task_name}\" in project \"{$project->name}\" ({$projectCode}).";
        } else {
            $title = 'New Major Task Created';
            $message = "Major task \"{$task->task_name}\" was created in project \"{$project->name}\" ({$projectCode}).";
        }

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'task_id' => $task->id,
            'task_name' => $task->task_name,
        ];

        if ($subtaskTitle !== null) {
            $data['subtask_title'] = $subtaskTitle;
        }

        $this->createForRecipients($recipients, 'new_task', $title, $message, $data);

        // Action 1: Adding a major task or sub task email notification
        $this->getEmailService()->sendTaskAddedEmail($project, $task, $subtaskTitle, $subtask);
    }

    /**
     * Notify all users (Admin, Manager, Staff) about a project warranty expiring in 7, 3, or 1 day(s).
     */
    public function notifyWarrantyExpiry(Project $project, int $daysRemaining = 1): void
    {
        $recipients = $this->getAllOperationalUsers();
        $projectCode = $project->project_code ?? 'N/A';
        $warrantyEnd = $project->warranty_end_date
            ? $project->warranty_end_date->format('M j, Y')
            : 'Unknown';

        $title = $daysRemaining === 1 ? 'Warranty Expiring Tomorrow' : "Warranty Expiring in {$daysRemaining} Days";
        $message = "The warranty for project \"{$project->name}\" ({$projectCode}) expires in {$daysRemaining} day(s) on {$warrantyEnd}. Please review and take necessary action.";

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'days_remaining' => $daysRemaining,
            'warranty_end_date' => $project->warranty_end_date?->toDateString(),
            'url' => route('projects.show', $project->id),
        ];

        // Avoid duplicate warranty notifications for the same project and day threshold today
        $existsToday = Notification::where('type', 'warranty_expiry')
            ->whereDate('created_at', now()->toDateString())
            ->whereJsonContains('data->project_id', $project->id)
            ->whereJsonContains('data->days_remaining', $daysRemaining)
            ->exists();

        if (!$existsToday) {
            $this->createForRecipients($recipients, 'warranty_expiry', $title, $message, $data);
            $this->getEmailService()->sendWarrantyWarningEmail($project, $daysRemaining);
        }
    }

    /**
     * Notify Admin and Manager users about an expense / costing entry logged on a project.
     */
    public function notifyExpenseLogged(Project $project, ProjectCost $cost): void
    {
        $recipients = $this->getAdminAndManagerUsers();
        $projectCode = $project->project_code ?? 'N/A';
        $amountFormatted = '₱' . number_format((float) $cost->amount, 2);

        $title = 'New Expense Logged';
        $message = "Expense \"{$cost->description}\" ({$amountFormatted}) was logged on project \"{$project->name}\" ({$projectCode}).";

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'cost_id' => $cost->id,
            'amount' => $cost->amount,
            'description' => $cost->description,
            'cost_type' => $cost->cost_type,
            'url' => route('costing.show', $project->id),
        ];

        $this->createForRecipients($recipients, 'expense_logged', $title, $message, $data);
    }

    /**
     * Notify about password or email reset / update.
     * Targeted at Admin users plus the affected user.
     */
    public function notifyCredentialReset(User $user, string $action = 'password'): void
    {
        $adminRecipients = $this->getAdminAndManagerUsers();
        $recipients = $adminRecipients->push($user)->unique('id');

        $accountLabel = $user->employee_number ? "ID: {$user->employee_number}" : $user->email;

        if ($action === 'email') {
            $title = 'Security: Email Updated';
            $message = "The email address for account {$user->name} ({$accountLabel}) was updated to {$user->email}.";
        } elseif ($action === 'reset_link_sent') {
            $title = 'Security: Reset Link Dispatched';
            $message = "A password reset link was dispatched to {$user->name} ({$accountLabel}).";
        } else {
            $title = 'Security: Password Changed';
            $message = "The password for account {$user->name} ({$accountLabel}) was recently reset or changed.";
        }

        $data = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $action,
            'url' => route('account.settings'),
        ];

        $this->createForRecipients($recipients, 'credential_reset', $title, $message, $data);

        if ($action === 'password') {
            $this->getEmailService()->sendPasswordChangedEmail($user);
        }
    }

    /**
     * Notify Admin and the affected user when their password was successfully changed.
     * Also dispatches the PasswordChangedMail to the affected user.
     */
    public function notifyPasswordChanged(User $user, ?string $ipAddress = null): void
    {
        $adminRecipients = $this->getAdminAndManagerUsers();
        $recipients = $adminRecipients->push($user)->unique('id');

        $accountLabel = $user->employee_number ? "ID: {$user->employee_number}" : $user->email;
        $title = 'Security: Password Changed';
        $message = "The password for account {$user->name} ({$accountLabel}) was recently changed.";

        $data = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => 'password',
            'ip_address' => $ipAddress,
            'url' => route('account.settings'),
        ];

        $this->createForRecipients($recipients, 'credential_reset', $title, $message, $data);

        // Send security alert email to the affected user
        $this->getEmailService()->sendPasswordChangedEmail($user, $ipAddress);
    }

    /**
     * Notify Admin, Manager, and Staff users when a major task is completed (100%).
     */
    public function notifyMajorTaskCompleted(Project $project, ProjectTask $task): void
    {
        $recipients = $this->getAllOperationalUsers();
        $projectCode = $project->project_code ?? 'N/A';

        $title = 'Major Task Completed';
        $message = "Major task \"{$task->task_name}\" in project \"{$project->name}\" ({$projectCode}) has been completed.";

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'task_id' => $task->id,
            'task_name' => $task->task_name,
            'url' => route('projects.show', $project->id),
        ];

        // Avoid duplicate notification today for the same task completion
        $existsToday = Notification::where('type', 'major_task_completed')
            ->whereDate('created_at', now()->toDateString())
            ->whereJsonContains('data->task_id', $task->id)
            ->exists();

        if (!$existsToday) {
            $this->createForRecipients($recipients, 'major_task_completed', $title, $message, $data);
        }
    }

    /**
     * Notify Admin, Manager, and Staff users when a specific task or major task is due within the next 2 days.
     */
    public function notifyUpcomingDueTask(Project $project, ProjectTask $task, ?ProjectSubtask $subtask = null): void
    {
        $recipients = $this->getAllOperationalUsers();
        $projectCode = $project->project_code ?? 'N/A';

        if ($subtask !== null) {
            $dueDate = $subtask->due_date ? $subtask->due_date->format('M j, Y') : 'soon';
            $daysLeft = $subtask->due_date ? (int) ceil(now()->startOfDay()->diffInDays($subtask->due_date->startOfDay(), false)) : 0;
            $relativeText = $daysLeft <= 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : "in {$daysLeft} days");

            $title = 'Task Due Soon';
            $message = "Specific task \"{$subtask->title}\" under \"{$task->task_name}\" in project \"{$project->name}\" ({$projectCode}) is due {$relativeText} ({$dueDate}).";

            $data = [
                'project_id' => $project->id,
                'project_code' => $projectCode,
                'project_name' => $project->name,
                'task_id' => $task->id,
                'subtask_id' => $subtask->id,
                'subtask_title' => $subtask->title,
                'due_date' => $subtask->due_date?->toDateString(),
                'url' => route('projects.show', $project->id),
            ];

            $existsToday = Notification::where('type', 'task_due_soon')
                ->whereDate('created_at', now()->toDateString())
                ->whereJsonContains('data->subtask_id', $subtask->id)
                ->exists();

            if (!$existsToday) {
                $this->createForRecipients($recipients, 'task_due_soon', $title, $message, $data);
                $this->getEmailService()->sendTaskDueSoonEmail($project, $task, $subtask);
            }
        } else {
            $dueDate = $task->end_date ? $task->end_date->format('M j, Y') : 'soon';
            $daysLeft = $task->end_date ? (int) ceil(now()->startOfDay()->diffInDays($task->end_date->startOfDay(), false)) : 0;
            $relativeText = $daysLeft <= 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : "in {$daysLeft} days");

            $title = 'Major Task Due Soon';
            $message = "Major task \"{$task->task_name}\" in project \"{$project->name}\" ({$projectCode}) is due {$relativeText} ({$dueDate}).";

            $data = [
                'project_id' => $project->id,
                'project_code' => $projectCode,
                'project_name' => $project->name,
                'task_id' => $task->id,
                'task_name' => $task->task_name,
                'due_date' => $task->end_date?->toDateString(),
                'url' => route('projects.show', $project->id),
            ];

            $existsToday = Notification::where('type', 'task_due_soon')
                ->whereDate('created_at', now()->toDateString())
                ->whereJsonContains('data->task_id', $task->id)
                ->whereNull('data->subtask_id')
                ->exists();

            if (!$existsToday) {
                $this->createForRecipients($recipients, 'task_due_soon', $title, $message, $data);
                $this->getEmailService()->sendTaskDueSoonEmail($project, $task, null);
            }
        }
    }

    /**
     * Notify Admin and Manager users about a file uploaded to a project.
     */
    public function notifyFileUpload(Project $project, ProjectFile $file): void
    {
        $recipients = $this->getAdminAndManagerUsers();
        $projectCode = $project->project_code ?? 'N/A';

        $title = 'File Uploaded';
        $message = "File \"{$file->file_name}\" was uploaded to project \"{$project->name}\" ({$projectCode}).";

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'file_id' => $file->id,
            'file_name' => $file->file_name,
            'url' => route('projects.show', $project->id),
        ];

        $this->createForRecipients($recipients, 'file_uploaded', $title, $message, $data);
    }

    /**
     * Notify Admin and Manager users about manpower assigned to a project.
     */
    public function notifyManpowerAssigned(Project $project, \Illuminate\Support\Collection|array $personnelList): void
    {
        $recipients = $this->getAdminAndManagerUsers();
        $projectCode = $project->project_code ?? 'N/A';
        $list = collect($personnelList);

        if ($list->isEmpty()) {
            return;
        }

        $count = $list->count();
        if ($count === 1) {
            $person = $list->first();
            $title = 'Manpower Assigned';
            $message = "Personnel {$person->employee_id} ({$person->full_name} - {$person->expertise}) was assigned to project \"{$project->name}\" ({$projectCode}).";
        } else {
            $summary = $list->map(fn ($p) => "{$p->employee_id} ({$p->full_name})")->implode(', ');
            $title = 'Manpower Assigned';
            $message = "{$count} personnel assigned to project \"{$project->name}\" ({$projectCode}): {$summary}.";
        }

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'count' => $count,
            'url' => route('projects.show', $project->id),
        ];

        $this->createForRecipients($recipients, 'manpower_assigned', $title, $message, $data);
    }

    /**
     * Notify Admin and Manager users about a new material added to the catalog.
     */
    public function notifyNewMaterial(Resource $resource): void
    {
        $recipients = $this->getAdminAndManagerUsers();
        $code = $resource->resource_code ?? 'N/A';

        $title = 'New Material Added';
        $message = "Material \"{$resource->name}\" ({$code}) was added to the catalog with {$resource->quantity_available} initial stock.";

        $data = [
            'resource_id' => $resource->id,
            'resource_code' => $code,
            'resource_name' => $resource->name,
            'quantity' => $resource->quantity_available,
            'url' => route('resources.show', $resource->id),
        ];

        $this->createForRecipients($recipients, 'new_material', $title, $message, $data);
    }

    /**
     * Notify Admin, Manager, and Staff users when a catalog material's stock is running low (<= 8 units).
     */
    public function notifyLowStockMaterial(Resource $resource): void
    {
        // Avoid duplicate dispatch within the same request cycle
        if (isset(self::$lowStockDispatchedInRequest[$resource->id])) {
            return;
        }
        self::$lowStockDispatchedInRequest[$resource->id] = true;

        $recipients = $this->getAllOperationalUsers();
        $code = $resource->resource_code ?? 'N/A';

        $title = 'Low Stock Warning';
        $message = "Stock for material \"{$resource->name}\" ({$code}) is running low ({$resource->quantity_available} unit(s) remaining).";

        $data = [
            'resource_id' => $resource->id,
            'resource_code' => $code,
            'resource_name' => $resource->name,
            'quantity' => $resource->quantity_available,
            'url' => route('resources.show', $resource->id),
        ];

        $this->createForRecipients($recipients, 'low_stock', $title, $message, $data);
        $this->getEmailService()->sendMaterialLowStockEmail($resource);
    }

    /**
     * Notify Admin and Manager users that a Staff member performed an action awaiting their review.
     */
    public function notifyStaffActionPending(StaffPendingAction $pendingAction): void
    {
        $recipients = $this->getAdminAndManagerUsers();
        $pendingAction->loadMissing(['user', 'project']);

        $staffName = $pendingAction->user->name ?? 'Staff';
        $project = $pendingAction->project;
        $projectName = $project->name ?? 'Project';
        $projectCode = $project->project_code ?? 'N/A';
        $payload = $pendingAction->payload ?? [];

        [$title, $message] = match ($pendingAction->action_type) {
            'add_expense' => [
                'Staff Action Review: Expense Submission',
                "Staff member {$staffName} submitted an expense entry (\"" . ($payload['description'] ?? 'Expense') . "\", ₱" . number_format((float) ($payload['amount'] ?? 0), 2) . ") on project \"{$projectName}\" ({$projectCode}) requiring review.",
            ],
            'complete_subtask' => [
                'Staff Action Review: Task Completed',
                "Staff member {$staffName} marked task \"" . ($payload['subtask_title'] ?? 'Task') . "\" completed on project \"{$projectName}\" ({$projectCode}) requiring review.",
            ],
            'add_file' => [
                'Staff Action Review: File Upload',
                "Staff member {$staffName} uploaded a file (\"" . ($payload['file_name'] ?? 'File') . "\") on project \"{$projectName}\" ({$projectCode}) requiring review.",
            ],
            default => [
                'Staff Action Review Required',
                "Staff member {$staffName} submitted an action on project \"{$projectName}\" ({$projectCode}) requiring review.",
            ],
        };

        $data = [
            'pending_action_id' => $pendingAction->id,
            'action_type' => $pendingAction->action_type,
            'project_id' => $project->id,
            'project_name' => $projectName,
            'project_code' => $projectCode,
            'staff_name' => $staffName,
            'staff_email' => $pendingAction->user->email ?? '',
            'payload' => $payload,
            'status' => 'pending',
            'submitted_at' => $pendingAction->created_at?->format('M j, Y · g:i A'),
            'url' => route('projects.show', $project->id),
        ];

        // Notify Admin & Manager reviewers
        $this->createForRecipients($recipients, 'staff_action_review', $title, $message, $data);

        // Action 6: Send email to manager / admin informing them of pending review (add_expense or complete_subtask)
        if (in_array($pendingAction->action_type, ['add_expense', 'complete_subtask'], true)) {
            $this->getEmailService()->sendStaffActionPendingReviewEmail($pendingAction);
        }

        // Also notify the submitting Staff member that their action is waiting for approval
        $this->notifyStaffActionSubmitted($pendingAction);
    }

    /**
     * Notify the submitting Staff user that their action is waiting for approval.
     */
    public function notifyStaffActionSubmitted(StaffPendingAction $pendingAction): void
    {
        $pendingAction->loadMissing(['user', 'project']);
        $recipient = $pendingAction->user;
        if (!$recipient) {
            return;
        }

        $projectName = $pendingAction->project->name ?? 'Project';
        $projectCode = $pendingAction->project->project_code ?? 'N/A';
        $payload = $pendingAction->payload ?? [];

        $actionDetails = match ($pendingAction->action_type) {
            'add_expense' => 'expense entry ("' . ($payload['description'] ?? 'Expense') . '", ₱' . number_format((float) ($payload['amount'] ?? 0), 2) . ')',
            'complete_subtask' => 'task completion request for "' . ($payload['subtask_title'] ?? 'Task') . '"',
            'add_file' => 'file upload ("' . ($payload['file_name'] ?? 'File') . '")',
            default => 'action request',
        };

        $title = 'Action Waiting For Approval';
        $message = "Your {$actionDetails} on project \"{$projectName}\" ({$projectCode}) has been submitted and is waiting for approval by your manager or administrator.";

        $data = [
            'pending_action_id' => $pendingAction->id,
            'project_id' => $pendingAction->project_id,
            'action_type' => $pendingAction->action_type,
            'status' => 'waiting_approval',
            'url' => route('projects.show', $pendingAction->project_id),
        ];

        $this->createForRecipients(collect([$recipient]), 'staff_action_pending', $title, $message, $data);
    }

    /**
     * Notify Admin and Manager users that a Staff member sent a reminder/ping for their pending request.
     */
    public function notifyStaffActionPing(StaffPendingAction $pendingAction): void
    {
        $recipients = $this->getAdminAndManagerUsers();
        $pendingAction->loadMissing(['user', 'project']);

        $staffName = $pendingAction->user->name ?? 'Staff';
        $project = $pendingAction->project;
        $projectName = $project->name ?? 'Project';
        $projectCode = $project->project_code ?? 'N/A';
        $payload = $pendingAction->payload ?? [];

        $actionName = match ($pendingAction->action_type) {
            'add_expense' => 'expense entry ("' . ($payload['description'] ?? 'Expense') . '", ₱' . number_format((float) ($payload['amount'] ?? 0), 2) . ')',
            'complete_subtask' => 'task completion ("' . ($payload['subtask_title'] ?? 'Task') . '")',
            'add_file' => 'file upload ("' . ($payload['file_name'] ?? 'File') . '")',
            default => 'request',
        };

        $title = 'Staff Request Reminder (Ping)';
        $message = "Staff member {$staffName} sent a reminder to review their pending {$actionName} on project \"{$projectName}\" ({$projectCode}).";

        $data = [
            'pending_action_id' => $pendingAction->id,
            'action_type' => $pendingAction->action_type,
            'project_id' => $project->id,
            'project_name' => $projectName,
            'project_code' => $projectCode,
            'staff_name' => $staffName,
            'staff_email' => $pendingAction->user->email ?? '',
            'payload' => $payload,
            'status' => 'pending',
            'is_reminder' => true,
            'pinged_at' => now()->format('M j, Y · g:i A'),
            'url' => route('projects.show', $project->id),
        ];

        $this->createForRecipients($recipients, 'staff_action_review', $title, $message, $data);
    }

    /**
     * Notify a Staff user about the review outcome of their submitted action.
     */
    public function notifyStaffActionReviewed(StaffPendingAction $pendingAction, string $decision = 'approved'): void
    {
        $pendingAction->loadMissing(['user', 'project']);
        $recipient = $pendingAction->user;
        if (!$recipient) {
            return;
        }

        $projectName = $pendingAction->project->name ?? 'Project';
        $projectCode = $pendingAction->project->project_code ?? 'N/A';
        $actionLabel = match ($pendingAction->action_type) {
            'add_expense' => 'expense entry (' . ($pendingAction->payload['description'] ?? 'Expense') . ')',
            'complete_subtask' => 'task completion (' . ($pendingAction->payload['subtask_title'] ?? 'Task') . ')',
            'add_file' => 'file upload (' . ($pendingAction->payload['file_name'] ?? 'File') . ')',
            default => 'action',
        };

        $title = $decision === 'approved' ? 'Action Approved' : 'Action Rejected';
        $message = $decision === 'approved'
            ? "Your {$actionLabel} on project \"{$projectName}\" ({$projectCode}) has been accepted and reflected in the system."
            : "Your {$actionLabel} on project \"{$projectName}\" ({$projectCode}) was reviewed and rejected.";

        $data = [
            'pending_action_id' => $pendingAction->id,
            'project_id' => $pendingAction->project_id,
            'action_type' => $pendingAction->action_type,
            'decision' => $decision,
            'url' => route('projects.show', $pendingAction->project_id),
        ];

        // Mark the prior waiting-for-approval notification as read
        Notification::where('user_id', $recipient->id)
            ->where('type', 'staff_action_pending')
            ->whereJsonContains('data->pending_action_id', $pendingAction->id)
            ->update(['read_at' => now()]);

        $this->createForRecipients(collect([$recipient]), 'staff_action_decision', $title, $message, $data);

        // Action 7: Result of the request done by a staff (for staff users only)
        $this->getEmailService()->sendStaffActionResultEmail($pendingAction, $decision);
    }

    /**
     * Retrieve all active users who hold the Admin or Manager role.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function getAdminAndManagerUsers(): \Illuminate\Support\Collection
    {
        return User::query()
            ->withoutTrashed()
            ->where(function ($query) {
                $query->whereNull('locked_until')
                    ->orWhere('locked_until', '<=', now());
            })
            ->where(function ($q) {
                $q->whereHas('roles', function ($rq) {
                    $rq->whereIn('name', ['Admin', 'Manager']);
                })->orWhereIn('job_title', ['Admin', 'Manager', 'Project Manager', 'General Manager', 'Administrator']);
            })
            ->get();
    }

    /**
     * Retrieve all active operational users (Admin, Manager, and Staff).
     * SoftDeletes on User automatically excludes archived/deactivated accounts.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function getAllOperationalUsers(): \Illuminate\Support\Collection
    {
        return User::query()
            ->withoutTrashed()
            ->where(function ($query) {
                $query->whereNull('locked_until')
                    ->orWhere('locked_until', '<=', now());
            })
            ->get();
    }

    /**
     * Bulk-create notification records for a set of recipient users.
     *
     * Uses a single transaction for consistency across all inserts.
     *
     * @param \Illuminate\Support\Collection<int, User> $recipients
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array<string, mixed> $data
     */
    private function createForRecipients(
        \Illuminate\Support\Collection $recipients,
        string $type,
        string $title,
        string $message,
        array $data,
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $recipients->map(fn (User $user) => [
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => json_encode($data),
            'read_at' => null,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        DB::transaction(function () use ($rows) {
            // Insert in chunks to avoid hitting MySQL max packet size on large teams
            foreach (array_chunk($rows, 100) as $chunk) {
                Notification::insert($chunk);
            }
        });
    }
}
