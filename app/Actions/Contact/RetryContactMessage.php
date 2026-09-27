<?php

namespace App\Actions\Contact;

use App\Enums\ContactMessageStatus;
use App\Jobs\SendContactMessage;
use App\Models\ContactMessage;

/**
 * Queue another delivery attempt of an undelivered message. A message that
 * was already sent is never re-dispatched (idempotency).
 */
class RetryContactMessage
{
    /**
     * @return bool Whether a new delivery attempt was queued.
     */
    public function handle(ContactMessage $contactMessage): bool
    {
        $updated = ContactMessage::query()
            ->whereKey($contactMessage->id)
            ->where('status', '!=', ContactMessageStatus::Sent->value)
            ->update([
                'status' => ContactMessageStatus::Pending->value,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return false;
        }

        SendContactMessage::dispatch($contactMessage->id);

        return true;
    }
}
