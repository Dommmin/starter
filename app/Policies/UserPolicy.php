<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only administrators manage user accounts. Nobody deletes their own
 * account from the panel (that is the self-service settings flow). Business
 * guards that depend on other rows (last administrator, own role) live in
 * the user actions.
 */
class UserPolicy
{
    /**
     * Determine whether the user can list and manage user accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create accounts.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can edit the account and its role.
     */
    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the account.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->isNot($target);
    }
}
