<?php

namespace App\Policies;

use App\Models\User;

/**
 * The audit log is read-only for administrators; no ability allows editing
 * or deleting entries.
 */
class AuditLogPolicy
{
    /**
     * Determine whether the user can browse the audit log.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
