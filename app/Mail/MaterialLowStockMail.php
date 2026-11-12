<?php

namespace App\Mail;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MaterialLowStockMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public Resource $resource,
        public ?string $actionUrl = null,
    ) {
        $this->actionUrl = $this->actionUrl ?? route('resources.show', $this->resource->id);
    }

    public function envelope(): Envelope
    {
        $code = $this->resource->resource_code ?? 'N/A';

        return new Envelope(
            subject: "[Dex PMS] Low Stock Alert - {$this->resource->name} ({$code})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.material-low-stock',
            with: [
                'recipient' => $this->recipient,
                'resource' => $this->resource,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
