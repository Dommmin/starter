<?php

namespace App\Data\Admin\Contact;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin contact message list (no message body).
 */
#[TypeScript]
class ContactMessageListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $locale,
        public ContactMessageStatus $status,
        public int $attempts,
        public ?string $createdAt,
    ) {}

    public static function fromModel(ContactMessage $contactMessage): self
    {
        return new self(
            id: $contactMessage->id,
            name: $contactMessage->name,
            email: $contactMessage->email,
            locale: $contactMessage->locale,
            status: $contactMessage->status,
            attempts: $contactMessage->attempts,
            createdAt: $contactMessage->created_at?->toIso8601String(),
        );
    }
}
