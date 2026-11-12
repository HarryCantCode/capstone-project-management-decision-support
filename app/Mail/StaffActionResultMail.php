<?php

namespace App\Mail;

use App\Models\StaffPendingAction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffActionResultMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public StaffPendingAction $pendingAction,
        public string $decision = 'approved',
        public ?string $actionUrl = null,
    ) {
        $this->pendingAction->loadMissing(['user', 'project', 'reviewer']);
        $this->actionUrl = $this->actionUrl ?? route('approval-list.index');
    }

    public function envelope(): Envelope
    {
        $project = $this->pendingAction->project;
        $projectName = $project->name ?? 'Project';
        $label = $this->pendingAction->action_type_label ?? 'Request';

        $subject = $this->decision === 'approved'
            ? "[Dex PMS] Request Approved: {$label} ({$projectName})"
            : "[Dex PMS] Request Rejected: {$label} ({$projectName})";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $project = $this->pendingAction->project;
        $reviewer = $this->pendingAction->reviewer;
        $payload = $this->pendingAction->payload ?? [];

        $actionSummary = match ($this->pendingAction->action_type) {
            'add_expense' => ($payload['description'] ?? 'Expense') . ' (₱' . number_format((float) ($payload['amount'] ?? 0), 2) . ')',
            'complete_subtask' => ($payload['subtask_title'] ?? $payload['subtask_name'] ?? 'Task completion'),
            default => 'Staff request',
        };

        return new Content(
            view: 'emails.staff-action-result',
            with: [
                'recipient' => $this->recipient,
                'pendingAction' => $this->pendingAction,
                'project' => $project,
                'reviewer' => $reviewer,
                'decision' => $this->decision,
                'isApproved' => $this->decision === 'approved',
                'actionTypeLabel' => $this->pendingAction->action_type_label ?? 'Staff Action',
                'actionSummary' => $actionSummary,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
