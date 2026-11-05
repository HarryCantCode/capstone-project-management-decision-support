<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeviceVerificationOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
        public array $deviceInfo,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dex PMS Security - Verification Code for New Device Login',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.device-otp',
        );
    }
}
