<?php

namespace App\Jobs;

use App\Enums\ContactMessageStatus;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deliver one contact message to the configured recipient. Idempotent: a
 * message already marked `sent` is skipped, and only one job per message
 * may be queued at a time (ShouldBeUnique). A crash after the mailer
 * accepted the mail but before the status is saved can still produce a
 * duplicate (documented P0 limitation, document 01).
 */
class SendContactMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Attempts per dispatch; `contact:retry-failed` may dispatch again.
     */
    public int $tries = 4;

    public int $timeout = 30;

    /**
     * Upper bound of the uniqueness lock (covers the whole backoff chain).
     */
    public int $uniqueFor = 3600;

    public function __construct(public readonly int $contactMessageId) {}

    public function uniqueId(): string
    {
        return (string) $this->contactMessageId;
    }

    /**
     * Exponential backoff between attempts, in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(LocalizationConfig $localization): void
    {
        $contactMessage = ContactMessage::query()->find($this->contactMessageId);

        if ($contactMessage === null || $contactMessage->status === ContactMessageStatus::Sent) {
            return;
        }

        $contactMessage->forceFill([
            'status' => ContactMessageStatus::Pending,
            'attempts' => $contactMessage->attempts + 1,
        ])->save();

        try {
            Mail::to((string) config('contact.recipient'))
                ->locale($localization->getAdminDefault())
                ->send(new ContactMessageMail($contactMessage));
        } catch (Throwable $exception) {
            $contactMessage->forceFill(['last_error' => self::errorSummary($exception)])->save();

            throw $exception;
        }

        $contactMessage->forceFill([
            'status' => ContactMessageStatus::Sent,
            'sent_at' => now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * All attempts of this dispatch failed.
     */
    public function failed(?Throwable $exception): void
    {
        ContactMessage::query()
            ->whereKey($this->contactMessageId)
            ->where('status', '!=', ContactMessageStatus::Sent->value)
            ->update([
                'status' => ContactMessageStatus::Failed->value,
                'last_error' => $exception !== null ? self::errorSummary($exception) : 'Unknown failure',
                'updated_at' => now(),
            ]);
    }

    /**
     * PII-free failure summary: the exception class and code only. Mailer
     * messages may echo addresses or message content, so they are not stored.
     */
    public static function errorSummary(Throwable $exception): string
    {
        $code = $exception->getCode();

        return Str::limit(
            class_basename($exception).(is_int($code) && $code !== 0 ? " (code {$code})" : ''),
            250,
        );
    }
}
