<?php

namespace App\Policies;

use App\Models\SavedListView;
use App\Models\User;

/**
 * A saved list view belongs only to the user who saved it; nobody else,
 * administrators included, sees or removes it.
 */
class SavedListViewPolicy
{
    /**
     * Determine whether the user can remove the view.
     */
    public function delete(User $user, SavedListView $view): bool
    {
        return $user->id === $view->user_id;
    }
}
