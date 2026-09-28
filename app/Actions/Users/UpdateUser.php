<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update the name, email and panel role of an account with optimistic
 * locking. Administrators cannot change their own role, and the last
 * administrator cannot be demoted. A new email address must be verified
 * again. Name and email changes are audited without their values
 * (`user.updated`); role changes go through AssignUserRole.
 */
class UpdateUser
{
    public function __construct(
        private readonly AssignUserRole $assignUserRole,
        private readonly GuardLastAdministrator $guardLastAdministrator,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @throws ValidationException On a stale version (`conflict`) or a forbidden role change (`role`).
     */
    public function handle(User $actor, User $user, string $name, string $email, ?UserRole $role, string $expectedUpdatedAt): User
    {
        $emailChanged = false;

        $user = DB::transaction(function () use ($actor, $user, $name, $email, $role, $expectedUpdatedAt, &$emailChanged): User {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.users.conflict'),
                ]);
            }

            if ($locked->role !== $role) {
                if ($locked->is($actor)) {
                    throw ValidationException::withMessages([
                        'role' => __('admin.users.cannotChangeOwnRole'),
                    ]);
                }

                if ($locked->role === UserRole::Admin) {
                    $this->guardLastAdministrator->handle($locked, 'role');
                }
            }

            $locked->fill(['name' => $name, 'email' => $email]);
            $emailChanged = $locked->isDirty('email');

            $changes = [];
            if ($locked->isDirty('name')) {
                $changes['name'] = RecordAuditEvent::redacted();
            }

            if ($emailChanged) {
                $changes['email'] = RecordAuditEvent::redacted();
                $locked->email_verified_at = null;
            }

            $locked->save();

            if ($changes !== []) {
                $this->audit->handle(AuditAction::UserUpdated, $locked, $actor, $changes);
            }

            $this->assignUserRole->handle($locked, $role, $actor);

            return $locked;
        });

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }
}
