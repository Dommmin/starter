<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Assign or revoke (null) the panel role of a user and audit the change as
 * `user.role_changed` in the same transaction. An unchanged role is not
 * audited.
 */
class AssignUserRole
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  User|null  $actor  Null when the change comes from the console.
     */
    public function handle(User $user, ?UserRole $role, ?User $actor): void
    {
        DB::transaction(function () use ($user, $role, $actor): void {
            $previousRole = $user->role;

            $user->assignRole($role);

            if ($previousRole === $role) {
                return;
            }

            $this->audit->handle(AuditAction::UserRoleChanged, $user, $actor, [
                'role' => RecordAuditEvent::change($previousRole?->value, $role?->value),
            ]);
        });
    }
}
