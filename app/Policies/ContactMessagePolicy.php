<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/**
 * Admins and editors read contact messages; a viewer does not (personal
 * data of senders). Only administrators trigger a new delivery, delete a
 * message or export the list.
 */
class ContactMessagePolicy
{
    /**
     * Determine whether the user can list contact messages.
     */
    public function viewAny(User $user): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can read the message.
     */
    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can queue another delivery attempt.
     */
    public function retry(User $user, ContactMessage $contactMessage): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the message.
     */
    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can export the filtered list as CSV.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }
}
