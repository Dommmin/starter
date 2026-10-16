<?php

namespace App\Data\Admin\Contact;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactWebhookStatus;
use App\Models\ContactMessage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full contact message with its delivery state.
 */
#[TypeScript]
class ContactMessageDetailData extends Data
{
    /**
     * @param  list<ContactMessageExtraFieldData>  $extraFields
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $message,
        public string $locale,
        public ContactMessageStatus $status,
        public int $attempts,
        public ?string $lastError,
        public ?string $sentAt,
        public ?string $createdAt,
        public ?ContactWebhookStatus $webhookStatus = null,
        public int $webhookAttempts = 0,
        public ?string $formTitle = null,
        public array $extraFields = [],
    ) {}

    /**
     * Requires `form.fields` to be loaded.
     */
    public static function fromModel(ContactMessage $contactMessage, string $locale = 'en', string $fallback = 'en'): self
    {
        return new self(
            id: $contactMessage->id,
            name: $contactMessage->name,
            email: $contactMessage->email,
            message: $contactMessage->message,
            locale: $contactMessage->locale,
            status: $contactMessage->status,
            attempts: $contactMessage->attempts,
            lastError: $contactMessage->last_error,
            sentAt: $contactMessage->sent_at?->toIso8601String(),
            createdAt: $contactMessage->created_at?->toIso8601String(),
            webhookStatus: $contactMessage->webhook_status,
            webhookAttempts: $contactMessage->webhook_attempts,
            formTitle: $contactMessage->form?->titleIn($locale, $fallback),
            extraFields: array_map(
                fn (array $row): ContactMessageExtraFieldData => new ContactMessageExtraFieldData($row['label'], $row['value']),
                $contactMessage->extraFieldRows($locale, $fallback),
            ),
        );
    }
}
