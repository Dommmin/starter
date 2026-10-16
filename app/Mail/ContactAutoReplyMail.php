<?php

namespace App\Mail;

use App\Data\Settings\SiteSettingsData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Automatic reply to the sender of a contact message, in the sender's
 * language, with the site brand and contact details. It never repeats the
 * submitted message, so the form cannot relay arbitrary text to any
 * address. Sent synchronously by SendContactAutoReply (which owns retries).
 */
class ContactAutoReplyMail extends Mailable
{
    public function __construct(
        public readonly SiteSettingsData $site,
        public readonly string $text,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->site->contact->email === null ? [] : [new Address($this->site->contact->email, $this->site->name)],
            subject: __('public.contactAutoReply.subject', ['site' => $this->site->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact.auto-reply',
            text: 'mail.contact.auto-reply-text',
            with: [
                'siteName' => $this->site->name,
                'logoUrl' => $this->site->logo?->src,
                'body' => $this->text,
                'contactEmail' => $this->site->contact->email,
                'contactPhone' => $this->site->contact->phone,
            ],
        );
    }
}
