<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WarrantyWarningMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public Project $project,
        public int $daysRemaining = 1,
        public ?string $actionUrl = null,
    ) {
        $this->actionUrl = $this->actionUrl ?? route('projects.show', $this->project->id);
    }

    public function envelope(): Envelope
    {
        $code = $this->project->project_code ?? 'N/A';
        $subject = match ($this->daysRemaining) {
            1 => "[Dex PMS] Critical: Project Warranty Expiring Tomorrow - {$this->project->name} ({$code})",
            default => "[Dex PMS] Warranty Warning: Expiring in {$this->daysRemaining} Days - {$this->project->name} ({$code})",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.warranty-warning',
            with: [
                'recipient' => $this->recipient,
                'project' => $this->project,
                'daysRemaining' => $this->daysRemaining,
                'warrantyEnd' => $this->project->warranty_end_date ? $this->project->warranty_end_date->format('M j, Y') : 'Unknown',
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
