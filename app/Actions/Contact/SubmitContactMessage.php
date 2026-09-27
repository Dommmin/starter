<?php

namespace App\Actions\Contact;

use App\Enums\ContactMessageStatus;
use App\Jobs\SendContactMessage;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\DB;

/**
 * Store a contact message as `pending` and queue its delivery once the
 * record is committed, so a lost job can be recovered from the database.
 */
class SubmitContactMessage
{
    public function handle(string $name, string $email, string $message, string $locale): ContactMessage
    {
        $contactMessage = DB::transaction(fn (): ContactMessage => ContactMessage::query()->create([
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'locale' => $locale,
            'status' => ContactMessageStatus::Pending,
        ]));

        SendContactMessage::dispatch($contactMessage->id)->afterCommit();

        return $contactMessage;
    }
}
