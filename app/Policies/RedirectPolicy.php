<?php

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;

/**
 * Redirects change how the whole public site answers (and what search
 * engines index), so only administrators list and manage them.
 */
class RedirectPolicy
{
    /**
     * Determine whether the user can list redirects in the panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create redirects.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can edit the redirect.
     */
    public function update(User $user, Redirect $redirect): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the redirect.
     */
    public function delete(User $user, Redirect $redirect): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can import redirects from CSV.
     */
    public function import(User $user): bool
    {
        return $user->isAdmin();
    }
}
