<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Create an account on behalf of an administrator (or the console): an
 * unusable random password, the chosen panel role (or none) and
 * `user.created` in the audit log, all in one transaction. The invitation
 * with a set-password link is queued after the commit; nobody sets or sees
 * a password. Only the console may pass a password instead (a server
 * without outgoing mail): the account is then verified and not invited.
 */
class CreateUser
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  User|null  $actor  Null when the account is created from the console.
     * @param  string|null  $password  Console only: a validated password chosen by the operator.
     */
    public function handle(?User $actor, string $name, string $email, ?UserRole $role, #[SensitiveParameter] ?string $password = null): User
    {
        $user = DB::transaction(function () use ($actor, $name, $email, $role, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password ?? Str::password(64),
            ]);

            if ($password !== null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

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

        if ($password === null) {
            $user->notify(new AccountInvitation);
        }

        return $user;
    }
}
