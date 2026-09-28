<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * Articles are written and published by the panel roles (admin and editor);
 * only administrators may delete an article with all its translations.
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
     * Determine whether the user can create articles.
     */
    public function create(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can edit the article and its translations.
     */
    public function update(User $user, Article $article): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can make a translation visible to visitors.
     */
    public function publish(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can permanently delete the article.
     */
    public function delete(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }
}
