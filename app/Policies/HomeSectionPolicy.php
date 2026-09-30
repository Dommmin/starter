<?php

namespace App\Policies;

use App\Models\HomeSection;
use App\Models\User;

/**
 * Home page sections are managed by the panel roles (admin and editor).
 * Sections are never created or deleted in the panel (one per type and
 * locale, see EnsureHomeSections), so there are no such abilities.
 */
class HomeSectionPolicy
{
    /**
     * Determine whether the user can list the sections in the panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can edit or toggle the section.
     */
    public function update(User $user, HomeSection $homeSection): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can change the order of the sections.
     */
    public function reorder(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }
}
