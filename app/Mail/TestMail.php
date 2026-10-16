<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Plain-text test message sent from the System page launch checklist.
 * Deliberately not queued: the result is shown to the administrator.
 */
class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('admin.launch.testMailSubject', ['site' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.system.test-text', with: ['sentAt' => now()->toIso8601String()]);
    }
}
