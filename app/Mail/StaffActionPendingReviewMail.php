<?php

namespace App\Mail;

use App\Models\StaffPendingAction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffActionPendingReviewMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public StaffPendingAction $pendingAction,
        public ?string $actionUrl = null,
    ) {
        $this->pendingAction->loadMissing(['user', 'project']);
        $this->actionUrl = $this->actionUrl ?? route('pending-actions.index');
    }

    public function envelope(): Envelope
    {
        $project = $this->pendingAction->project;
        $projectName = $project->name ?? 'Project';

        $subject = match ($this->pendingAction->action_type) {
            'add_expense' => "[Dex PMS] Action Required: Staff Expense Submission - {$projectName}",
            'complete_subtask' => "[Dex PMS] Action Required: Staff Task Completion - {$projectName}",
            default => "[Dex PMS] Action Required: Staff Request for Review - {$projectName}",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $project = $this->pendingAction->project;
        $staffUser = $this->pendingAction->user;
        $payload = $this->pendingAction->payload ?? [];

        $actionTitle = match ($this->pendingAction->action_type) {
            'add_expense' => 'Expense Submission (' . ($payload['description'] ?? 'Expense') . ')',
            'complete_subtask' => 'Task Completion (' . ($payload['subtask_title'] ?? $payload['subtask_name'] ?? 'Task') . ')',
            default => 'Action Request',
        };

        $actionTypeLabel = match ($this->pendingAction->action_type) {
            'add_expense' => 'Expense Submission',
            'complete_subtask' => 'Sub Task Completion',
            default => 'Staff Action',
        };

        return new Content(
            view: 'emails.staff-action-pending',
            with: [
                'recipient' => $this->recipient,
                'pendingAction' => $this->pendingAction,
                'project' => $project,
                'staffUser' => $staffUser,
                'payload' => $payload,
                'actionTitle' => $actionTitle,
                'actionTypeLabel' => $actionTypeLabel,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
