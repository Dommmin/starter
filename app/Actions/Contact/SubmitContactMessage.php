<?php

namespace App\Actions\Contact;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactWebhookStatus;
use App\Jobs\DeliverContactWebhook;
use App\Jobs\SendContactAutoReply;
use App\Jobs\SendContactMessage;
use App\Models\ContactForm;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\DB;

/**
 * Store a contact message as `pending` and, once the record is committed,
 * queue its delivery to the owner, the webhook (when configured) and the
 * auto-reply to the sender. Each runs as its own job, so a failing webhook
 * or auto-reply never loses the message; a lost job can be recovered from
 * the database.
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

        return $contactMessage;
    }
}
