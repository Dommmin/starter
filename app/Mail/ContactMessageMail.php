<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Contact form message delivered to the site owner. Sent synchronously by
 * the SendContactMessage job (which owns retries), in the panel language.
 * The visitor's address is used only as Reply-To, never as From.
 */
class ContactMessageMail extends Mailable
{
    public function __construct(public readonly ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contactMessage->email, $this->contactMessage->name)],
            subject: __('admin.contact.mail.subject', ['site' => (string) config('seo.site_name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact.message',
            text: 'mail.contact.message-text',
            with: [
                'senderName' => $this->contactMessage->name,
                'senderEmail' => $this->contactMessage->email,
                'body' => $this->contactMessage->message,
                'locale' => $this->contactMessage->locale,
                'submittedAt' => $this->contactMessage->created_at?->toIso8601String(),
            ],
        );
    }
}
