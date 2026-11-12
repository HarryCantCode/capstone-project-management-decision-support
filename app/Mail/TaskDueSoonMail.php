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

class TaskDueSoonMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public Project $project,
        public ProjectTask $task,
        public ?ProjectSubtask $subtask = null,
        public ?string $relativeUrgency = null,
        public ?string $dueDateFormatted = null,
        public ?string $actionUrl = null,
    ) {
        $this->actionUrl = $this->actionUrl ?? route('projects.show', $this->project->id);

        if ($this->dueDateFormatted === null) {
            $date = $this->subtask?->due_date ?? $this->task->end_date;
            $this->dueDateFormatted = $date ? $date->format('M j, Y') : 'Upcoming';
        }

        if ($this->relativeUrgency === null) {
            $targetDate = $this->subtask?->due_date ?? $this->task->end_date;
            if ($targetDate) {
                $days = (int) ceil(now()->startOfDay()->diffInDays($targetDate->startOfDay(), false));
                $this->relativeUrgency = match (true) {
                    $days <= 0 => 'Today',
                    $days === 1 => 'Tomorrow',
                    default => "in {$days} days",
                };
            } else {
                $this->relativeUrgency = 'Soon';
            }
        }
    }

    public function envelope(): Envelope
    {
        $taskName = $this->subtask ? $this->subtask->title : $this->task->task_name;

        return new Envelope(
            subject: "[Dex PMS] Task Due Soon - {$taskName} ({$this->project->name})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.task-due-soon',
            with: [
                'recipient' => $this->recipient,
                'project' => $this->project,
                'task' => $this->task,
                'subtask' => $this->subtask,
                'relativeUrgency' => $this->relativeUrgency,
                'dueDateFormatted' => $this->dueDateFormatted,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
