<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $changedAt;

    public function __construct(
        public User $recipient,
        ?string $changedAt = null,
        public ?string $ipAddress = null,
        public ?string $actionUrl = null,
    ) {
        $this->changedAt = $changedAt ?? Carbon::now()->format('F j, Y \a\t g:i A T');
        $this->actionUrl = $this->actionUrl ?? route('account.settings');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Dex PMS] Security Alert: Your Password Was Successfully Changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
            with: [
                'recipient' => $this->recipient,
                'changedAt' => $this->changedAt,
                'ipAddress' => $this->ipAddress,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
