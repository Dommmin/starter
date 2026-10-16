<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Content pages are edited and published by admins and editors; a viewer
 * lists, opens (read-only) and previews them. Only administrators may
 * delete a page with all its translations.
 */
class PagePolicy
{
    /**
     * Determine whether the user can list pages in the panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can open the page editor (read-only
     * without `update`).
     */
    public function view(User $user, Page $page): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can create pages.
     */
    public function create(User $user): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can edit the page and its translations.
     */
    public function update(User $user, Page $page): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can preview saved, also unpublished,
     * translations of the page (same access as opening the editor).
     */
    public function preview(User $user, Page $page): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can make a translation visible to visitors.
     */
    public function publish(User $user): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can permanently delete the page.
     */
    public function delete(User $user, Page $page): bool
    {
        return $user->isAdmin();
    }
}
