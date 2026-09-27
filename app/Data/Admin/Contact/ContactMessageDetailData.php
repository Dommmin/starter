<?php

namespace App\Data\Admin\Contact;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full contact message with its delivery state.
 */
#[TypeScript]
class ContactMessageDetailData extends Data
{
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
    ) {}

    public static function fromModel(ContactMessage $contactMessage): self
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
        );
    }
}
