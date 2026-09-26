<?php

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

/**
 * Faqs are managed by the panel roles (admin and editor); only
 * administrators may delete them.
 */
class FaqPolicy
{
    /**
     * Determine whether the user can list faqs in the panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can create faqs.
     */
    public function create(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can edit the faq.
     */
    public function update(User $user, Faq $faq): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can permanently delete the faq.
     */
    public function delete(User $user, Faq $faq): bool
    {
        return $user->isAdmin();
    }
}
