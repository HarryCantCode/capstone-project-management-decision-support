<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectCompletedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public Project $project,
        public ?string $actionUrl = null,
    ) {
        $this->actionUrl = $this->actionUrl ?? route('projects.show', $this->project->id);
    }

    public function envelope(): Envelope
    {
        $code = $this->project->project_code ?? 'N/A';

        return new Envelope(
            subject: "[Dex PMS] Project Completed - {$this->project->name} ({$code})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project-completed',
            with: [
                'recipient' => $this->recipient,
                'project' => $this->project,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
