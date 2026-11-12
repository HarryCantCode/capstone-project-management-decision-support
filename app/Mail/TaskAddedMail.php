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

class TaskAddedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public Project $project,
        public ProjectTask $task,
        public ?string $subtaskTitle = null,
        public ?ProjectSubtask $subtask = null,
        public ?string $actionUrl = null,
    ) {
        $this->actionUrl = $this->actionUrl ?? route('projects.show', $this->project->id);
    }

    public function envelope(): Envelope
    {
        $subject = $this->subtaskTitle !== null
            ? "[Dex PMS] New Sub Task Added - {$this->subtaskTitle} ({$this->project->name})"
            : "[Dex PMS] New Major Task Added - {$this->task->task_name} ({$this->project->name})";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.task-added',
            with: [
                'recipient' => $this->recipient,
                'project' => $this->project,
                'task' => $this->task,
                'subtask' => $this->subtask,
                'subtaskTitle' => $this->subtaskTitle,
                'isSubtask' => $this->subtaskTitle !== null,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
