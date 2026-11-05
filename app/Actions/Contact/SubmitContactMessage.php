<?php

namespace App\Actions\Contact;

use App\Enums\AssistantFeature;
use App\Enums\ContactMessageStatus;
use App\Enums\ContactWebhookStatus;
use App\Jobs\ClassifyContactMessage;
use App\Jobs\DeliverContactWebhook;
use App\Jobs\SendContactAutoReply;
use App\Jobs\SendContactMessage;
use App\Models\ContactForm;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Store a contact message as `pending` and, once the record is committed,
 * queue its delivery to the owner, the webhook (when configured) and the
 * auto-reply to the sender. Each runs as its own job, so a failing webhook
 * or auto-reply never loses the message; a lost job can be recovered from
 * the database. With the AI message classification available, the
 * category is suggested by a separate job that is queued last and whose
 * failure never affects the stored message or its delivery.
 */
class SubmitContactMessage
{
    /**
     * @param  array<string, string|bool>  $fields  Validated extra values of `$form`.
     */
    public function handle(string $name, string $email, string $message, string $locale, ?ContactForm $form = null, array $fields = []): ContactMessage
    {
        $contactMessage = DB::transaction(fn (): ContactMessage => ContactMessage::query()->create([
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'locale' => $locale,
            'contact_form_id' => $form?->id,
            'fields' => $fields === [] ? null : $fields,
            'status' => ContactMessageStatus::Pending,
            'webhook_status' => DeliverContactWebhook::isConfigured() ? ContactWebhookStatus::Pending : null,
        ]));

        SendContactMessage::dispatch($contactMessage->id)->afterCommit();
        SendContactAutoReply::dispatch($contactMessage->id)->afterCommit();

        if (DeliverContactWebhook::isConfigured()) {
            DeliverContactWebhook::dispatch($contactMessage->id)->afterCommit();
        }

        $this->queueClassification($contactMessage);

        return $contactMessage;
    }

    /**
     * Best effort: a failing queue or (on the sync driver) a failing
     * classification is logged without content and the submission succeeds.
     */
    private function queueClassification(ContactMessage $contactMessage): void
    {
        if (! AssistantFeature::MessageClassification->isAvailable()) {
            return;
        }

        try {
            ClassifyContactMessage::dispatch($contactMessage->id)->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('Contact message classification could not be queued.', [
                'contact_message_id' => $contactMessage->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
