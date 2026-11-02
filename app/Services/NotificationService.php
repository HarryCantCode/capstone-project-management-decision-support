<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectTask;
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
