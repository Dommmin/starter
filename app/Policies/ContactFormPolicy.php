<?php

namespace App\Policies;

use App\Models\ContactForm;
use App\Models\User;

/**
 * Only administrators configure contact forms (they decide which personal
 * data the site collects). The default form cannot be deleted: sections
 * and submissions fall back to it.
 */
class ContactFormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ContactForm $contactForm): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ContactForm $contactForm): bool
    {
        return $user->isAdmin() && $contactForm->key !== ContactForm::DEFAULT_KEY;
    }
}
