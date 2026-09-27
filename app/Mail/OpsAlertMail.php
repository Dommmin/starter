<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Plain-text operator alert. Deliberately not queued (see OpsAlerter).
 */
class OpsAlertMail extends Mailable
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(
        public readonly string $event,
        public readonly array $context,
        public readonly string $environment,
        public readonly ?string $release,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[%s][%s] %s', config('app.name'), $this->environment, $this->event),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.ops.alert-text',
            with: [
                'event' => $this->event,
                'context' => $this->context,
                'environment' => $this->environment,
                'release' => $this->release,
                'occurredAt' => now()->toIso8601String(),
            ],
        );
    }
}
