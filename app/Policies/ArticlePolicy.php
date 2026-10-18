<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * Articles are written and published by admins and editors; a viewer
 * lists, opens (read-only) and previews them. Only administrators may
 * move an article to the trash, restore it or empty the trash.
 */
class ArticlePolicy
{
    /**
     * Determine whether the user can list articles in the panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can open the article editor (read-only
     * without `update`).
     */
    public function view(User $user, Article $article): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can create articles.
     */
    public function create(User $user): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can edit the article and its translations.
     */
    public function update(User $user, Article $article): bool
    {
        return $user->canEditContent();
    }

    /**
     * Determine whether the user can preview saved, also unpublished,
     * translations of the article (same access as opening the editor).
     */
    public function preview(User $user, Article $article): bool
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
     * Determine whether the user can move the article to the trash.
     */
    public function delete(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can see the articles in the trash.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can bring the article back from the trash.
     */
    public function restore(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can remove the article from the trash for good.
     */
    public function forceDelete(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }
}
