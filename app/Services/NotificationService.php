<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectFile;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
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
    /**
     * Notify Admin and Manager users about a newly created project.
     */
    public function notifyNewProject(Project $project): void
    {
        $recipients = $this->getAdminAndManagerUsers();
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
    }

    /**
     * Notify Admin and Manager users about a new major task or specific sub task logged on a project.
     */
    public function notifyNewTask(Project $project, ProjectTask $task, ?string $subtaskTitle = null): void
    {
        $recipients = $this->getAdminAndManagerUsers();
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
    }

    /**
     * Notify Admin and Manager users about a project warranty expiring tomorrow.
     */
    public function notifyWarrantyExpiry(Project $project): void
    {
        $recipients = $this->getAdminAndManagerUsers();
        $projectCode = $project->project_code ?? 'N/A';
        $warrantyEnd = $project->warranty_end_date
            ? $project->warranty_end_date->format('M j, Y')
            : 'Unknown';

        $title = 'Warranty Expiring Tomorrow';
        $message = "The warranty for project \"{$project->name}\" ({$projectCode}) expires on {$warrantyEnd}. Please review and take necessary action.";

        $data = [
            'project_id' => $project->id,
            'project_code' => $projectCode,
            'project_name' => $project->name,
            'warranty_end_date' => $project->warranty_end_date?->toDateString(),
        ];

        // Avoid duplicate warranty notifications for the same project on the same day
        $existsToday = Notification::where('type', 'warranty_expiry')
            ->whereDate('created_at', now()->toDateString())
            ->whereJsonContains('data->project_id', $project->id)
            ->exists();

        if (!$existsToday) {
            $this->createForRecipients($recipients, 'warranty_expiry', $title, $message, $data);
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
        $adminRecipients = User::role('Admin')->get();
        $recipients = $adminRecipients->push($user)->unique('id');

        $accountLabel = $user->account_number ? "Acc: {$user->account_number}" : $user->email;

        if ($action === 'email') {
            $title = 'Security: Email Updated';
            $message = "The email address for account {$user->name} ({$accountLabel}) was updated to {$user->email}.";
        } else {
            $title = 'Security: Password Reset';
            $message = "The password for account {$user->name} ({$accountLabel}) was recently reset or changed.";
        }

        $data = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $action,
            'url' => route('account.settings'),
        ];

        $this->createForRecipients($recipients, 'credential_reset', $title, $message, $data);
    }

    /**
     * Notify Admin and Manager users when a major task is completed (100%).
     */
    public function notifyMajorTaskCompleted(Project $project, ProjectTask $task): void
    {
        $recipients = $this->getAdminAndManagerUsers();
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
     * Notify Admin and Manager users when a specific task or major task is due within the next 2 days.
     */
    public function notifyUpcomingDueTask(Project $project, ProjectTask $task, ?ProjectSubtask $subtask = null): void
    {
        $recipients = $this->getAdminAndManagerUsers();
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
     * Notify Admin and Manager users when a catalog material's stock is running low (< 5 units).
     */
    public function notifyLowStockMaterial(Resource $resource): void
    {
        $recipients = $this->getAdminAndManagerUsers();
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

        // Avoid duplicate low stock alert today for the same material
        $existsToday = Notification::where('type', 'low_stock')
            ->whereDate('created_at', now()->toDateString())
            ->whereJsonContains('data->resource_id', $resource->id)
            ->exists();

        if (!$existsToday) {
            $this->createForRecipients($recipients, 'low_stock', $title, $message, $data);
        }
    }

    /**
     * Retrieve all active users who hold the Admin or Manager role.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function getAdminAndManagerUsers(): \Illuminate\Support\Collection
    {
        return User::role(['Admin', 'Manager'])->get();
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
