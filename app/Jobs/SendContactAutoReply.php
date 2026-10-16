<?php

namespace App\Jobs;

use App\Mail\ContactAutoReplyMail;
use App\Models\ContactMessage;
use App\Repositories\Settings\SiteSettingsRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Send the automatic reply to the sender of one contact message, in the
 * message locale. Independent of the owner notification: a failing
 * auto-reply never loses or blocks the message. Idempotent: a sent reply
 * is remembered for a day, and only one job per message is queued.
 */
class SendContactAutoReply implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $contactMessageId) {}

    public function uniqueId(): string
    {
        return (string) $this->contactMessageId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SiteSettingsRepository $siteSettings): void
    {
        $contactMessage = ContactMessage::query()->find($this->contactMessageId);
        $sentKey = "contact:auto-reply:{$this->contactMessageId}";

        if ($contactMessage === null || Cache::has($sentKey)) {
            return;
        }

        $text = $siteSettings->autoReplyText($contactMessage->locale);

        if ($text === null) {
            return;
        }

        Mail::to($contactMessage->email)
            ->locale($contactMessage->locale)
            ->send(new ContactAutoReplyMail($siteSettings->current($contactMessage->locale), $text));

        Cache::put($sentKey, true, now()->addDay());
    }
}
