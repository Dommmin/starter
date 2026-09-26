<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can list and manage user accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
