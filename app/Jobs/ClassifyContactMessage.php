<?php

namespace App\Jobs;

use App\Actions\Assistant\Exceptions\AssistantBudgetExceeded;
use App\Actions\Assistant\Exceptions\AssistantUnavailable;
use App\Actions\Assistant\RunAssistantPrompt;
use App\Ai\Agents\MessageClassificationAgent;
use App\Enums\AssistantFeature;
use App\Enums\ContactMessageCategory;
use App\Models\ContactMessage;
use App\Support\Privacy\ContactDetailsRedactor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Classify one contact message with the AI assistant (stage 6) and store
 * only its category and the time of classification. Owner approval
 * 2026-11-05: the prompt holds only the subject (an extra field with the
 * key `subject`, when the form has one) and the body, with e-mail addresses
 * and phone numbers redacted; the sender's name, e-mail and other fields are
 * never sent.
 *
 * Idempotent: classified messages are skipped and the result is written only
 * while `classified_at` is still empty. The job never deletes anything (spam
 * is only marked) and runs apart from the mail and webhook jobs. A provider
 * failure is retried with backoff and then leaves the message unclassified;
 * a used up budget or a disabled feature leaves it unclassified at once.
 * Logs carry the message id and exception class, never the content.
 */
class ClassifyContactMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Upper bound of the body sent to the provider (the form allows 5000).
     */
    public const int BODY_MAX_LENGTH = 5000;

    public const int SUBJECT_MAX_LENGTH = 200;

    public int $tries = 3;

    /**
     * Above the provider timeout (`assistant.timeout`, 30 s) and below the
     * Redis `retry_after` (90 s) of the default queue connection.
     */
    public int $timeout = 60;

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

    public function handle(RunAssistantPrompt $runAssistantPrompt, ContactDetailsRedactor $redactor): void
    {
        $contactMessage = ContactMessage::query()
            ->select(['id', 'message', 'fields', 'classified_at'])
            ->find($this->contactMessageId);

        if ($contactMessage === null
            || $contactMessage->classified_at !== null
            || ! AssistantFeature::MessageClassification->isAvailable()) {
            return;
        }

        try {
            $structured = $runAssistantPrompt->handle(
                AssistantFeature::MessageClassification,
                new MessageClassificationAgent,
                self::prompt($contactMessage, $redactor),
                $contactMessage,
                null,
            );
        } catch (AssistantBudgetExceeded) {
            $this->logUnclassified('budget');

            return;
        } catch (AssistantUnavailable) {
            // Rethrown without the provider exception: its message may echo the prompt.
            throw new AssistantUnavailable('The AI provider failed while classifying a contact message.');
        }

        $category = is_string($structured['category'] ?? null)
            ? ContactMessageCategory::tryFrom($structured['category'])
            : null;

        if ($category === null) {
            $this->logUnclassified('invalid_output');

            return;
        }

        ContactMessage::query()
            ->whereKey($contactMessage->id)
            ->whereNull('classified_at')
            ->update([
                'category' => $category->value,
                'classified_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * All attempts failed; the message stays unclassified.
     */
    public function failed(?Throwable $exception): void
    {
        $this->logUnclassified('provider', $exception);
    }

    /**
     * The only content sent to the provider: subject and body, redacted
     * before they are cut, so no partial address survives the cut.
     */
    public static function prompt(ContactMessage $contactMessage, ContactDetailsRedactor $redactor): string
    {
        $subject = $contactMessage->fields['subject'] ?? null;
        $body = mb_substr($redactor->redact($contactMessage->message), 0, self::BODY_MAX_LENGTH);

        if (! is_string($subject) || trim($subject) === '') {
            return "Message:\n{$body}";
        }

        $subject = mb_substr($redactor->redact(trim($subject)), 0, self::SUBJECT_MAX_LENGTH);

        return "Subject: {$subject}\n\nMessage:\n{$body}";
    }

    private function logUnclassified(string $reason, ?Throwable $exception = null): void
    {
        Log::warning('Contact message left unclassified.', [
            'contact_message_id' => $this->contactMessageId,
            'reason' => $reason,
            'exception' => $exception !== null ? $exception::class : null,
        ]);
    }
}
