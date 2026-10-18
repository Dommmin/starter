<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Content pages are edited and published by admins and editors; a viewer
 * lists, opens (read-only) and previews them. Only administrators may
 * move a page to the trash, restore it or empty the trash.
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
     * Determine whether the user can move the page to the trash.
     */
    public function delete(User $user, Page $page): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can see the pages in the trash.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can bring the page back from the trash.
     */
    public function restore(User $user, Page $page): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can remove the page from the trash for good.
     */
    public function forceDelete(User $user, Page $page): bool
    {
        return $user->isAdmin();
    }
}
