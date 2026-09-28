<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Keep at least one account with the administrator role. Must run inside the
 * caller's transaction: it locks the administrator rows, so two concurrent
 * demotions or deletions cannot both pass the check.
 */
class GuardLastAdministrator
{
    /**
     * @throws ValidationException When the target is the only administrator.
     */
    public function handle(User $target, string $errorKey): void
    {
        $administratorIds = User::query()
            ->where('role', UserRole::Admin->value)
            ->lockForUpdate()
            ->pluck('id');

        if ($administratorIds->count() === 1 && $administratorIds->first() === $target->id) {
            throw ValidationException::withMessages([
                $errorKey => __('admin.users.lastAdministrator'),
            ]);
        }
    }
}
