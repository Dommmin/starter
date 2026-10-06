<?php

namespace App\Support\DemoContent;

use App\Actions\Users\DeleteUser;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample accounts of {@see DemoUserSeeder} (other than the sample
 * administrator, removed separately): matched by e-mail address, edited
 * when the account changed after creation.
 */
class DemoUsers implements DemoContentProvider
{
    public function __construct(private readonly DeleteUser $deleteUser) {}

    public function label(): string
    {
        return 'accounts';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return false;
    }

    public function records(): array
    {
        return array_values(User::query()
            ->whereIn('email', array_keys(DemoUserSeeder::USERS))
            ->orderBy('id')
            ->get()
            ->all());
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        $user = $this->user($record);

        return $user->updated_at !== null
            && $user->created_at !== null
            && $user->updated_at->gt($user->created_at);
    }

    public function describe(Model $record): string
    {
        return $this->user($record)->email;
    }

    public function delete(Model $record, User $actor): void
    {
        $this->deleteUser->handle($actor, $this->user($record));
    }

    private function user(Model $record): User
    {
        if (! $record instanceof User) {
            throw new InvalidArgumentException('Expected a user.');
        }

        return $record;
    }
}
