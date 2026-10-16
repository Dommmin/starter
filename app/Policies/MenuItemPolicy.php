<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

/**
 * Navigation menus are managed by admins and editors, including deletion
 * (human decision for the navigation-menu scope); a viewer sees the menus
 * and opens items read-only.
 */
class MenuItemPolicy
{
    /**
     * Determine whether the user can see the menus in the panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can open the menu item editor (read-only
     * without `update`).
     */
    public function view(User $user, MenuItem $menuItem): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can add menu items.
     */
    public function create(User $user): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can edit the menu item.
     */
    public function update(User $user, MenuItem $menuItem): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can change the order of menu items.
     */
    public function reorder(User $user): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can delete the menu item with its children.
     */
    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $user->canEditContent();
    }
}
