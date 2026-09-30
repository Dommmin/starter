<?php

namespace App\Policies;

use App\Models\User;

/**
 * Site settings (brand, contact details, SEO defaults, contact form
 * recipient) are managed by administrators only. The abilities are checked
 * against the class because the singleton row may not exist yet.
 */
class SiteSettingPolicy
{
    /**
     * Determine whether the user can open the site settings form.
     */
    public function view(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can change the site settings.
     */
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
