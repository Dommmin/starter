<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Permanently delete a selection of messages: all or nothing. Every message
 * is authorized for `delete` before any row is removed; ids that no longer
 * exist are skipped, so repeating the request is harmless (idempotent).
 */
class DeleteContactMessages
{
    /**
     * @param  list<int>  $ids
     * @return int Number of deleted messages.
     */
    public function handle(User $user, array $ids): int
    {
        return DB::transaction(function () use ($user, $ids): int {
            $messages = ContactMessage::query()
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            foreach ($messages as $message) {
                Gate::forUser($user)->authorize('delete', $message);
            }

            return ContactMessage::query()->whereKey($messages->modelKeys())->delete();
        });
    }
}
