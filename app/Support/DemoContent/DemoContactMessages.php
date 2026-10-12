<?php

namespace App\Support\DemoContent;

use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\ContactMessageSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample contact messages of {@see ContactMessageSeeder}, matched by their
 * sample e-mail address (generated enquiries also by their sample text). Messages are never edited (delivery retries only
 * change their status), so every match is removed, like in the admin panel.
 */
class DemoContactMessages implements DemoContentProvider
{
    public function label(): string
    {
        return 'contact messages';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return false;
    }

    public function records(): array
    {
        return array_values(ContactMessage::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('email', array_keys(ContactMessageSeeder::MESSAGES))
                ->orWhere('email', 'like', 'lead-%@example.org'))
            ->orderBy('id')
            ->get()
            ->filter(fn (ContactMessage $message): bool => array_key_exists($message->email, ContactMessageSeeder::MESSAGES)
                || ContactMessageSeeder::isStreamMessage($message))
            ->all());
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        $this->message($record);

        return false;
    }

    public function describe(Model $record): string
    {
        return $this->message($record)->email;
    }

    public function delete(Model $record, User $actor): void
    {
        $this->message($record)->delete();
    }

    private function message(Model $record): ContactMessage
    {
        if (! $record instanceof ContactMessage) {
            throw new InvalidArgumentException('Expected a contact message.');
        }

        return $record;
    }
}
