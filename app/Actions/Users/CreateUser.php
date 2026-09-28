<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create an account on behalf of an administrator: an unusable random
 * password, the chosen panel role (or none) and `user.created` in the audit
 * log, all in one transaction. The invitation with a set-password link is
 * queued after the commit; the administrator never sets or sees a password.
 */
class CreateUser
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, string $name, string $email, ?UserRole $role): User
    {
        $user = DB::transaction(function () use ($actor, $name, $email, $role): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::password(64),
            ]);

            if ($role !== null) {
                $user->assignRole($role);
            }

            $this->audit->handle(AuditAction::UserCreated, $user, $actor, [
                'name' => RecordAuditEvent::redacted(),
                'email' => RecordAuditEvent::redacted(),
                'role' => RecordAuditEvent::change(null, $role?->value),
            ]);

            return $user;
        });

        $user->notify(new AccountInvitation);

        return $user;
    }
}
