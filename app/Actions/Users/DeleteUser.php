<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permanently delete an account (self-deletion is denied by UserPolicy).
 * The last administrator cannot be deleted. Authored content and audit
 * entries keep existing with a null author (FK `nullOnDelete`); passkeys
 * are removed with the account. `user.deleted` records only the former role.
 */
class DeleteUser
{
    public function __construct(
        private readonly GuardLastAdministrator $guardLastAdministrator,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @throws ValidationException When the account is the last administrator (`user`).
     */
    public function handle(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $this->guardLastAdministrator->handle($locked, 'user');

            $this->audit->handle(AuditAction::UserDeleted, $locked, $actor, [
                'role' => RecordAuditEvent::change($locked->role?->value, null),
            ]);

            $locked->delete();
        });
    }
}
