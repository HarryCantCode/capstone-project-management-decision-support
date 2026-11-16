<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public Project $project,
        public ProjectTask $task,
        public ProjectSubtask $subtask,
        public ?string $actionUrl = null,
    ) {
        $this->actionUrl = $this->actionUrl ?? route('approval-list.index', ['tab' => 'assigned_tasks']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Dex PMS] Task Assignment: {$this->subtask->title} ({$this->project->name})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.task-assigned',
            with: [
                'recipient' => $this->recipient,
                'project' => $this->project,
                'task' => $this->task,
                'subtask' => $this->subtask,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
