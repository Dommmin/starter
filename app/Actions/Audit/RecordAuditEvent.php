<?php

namespace App\Actions\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append one audit entry. Domain actions call it explicitly inside the same
 * database transaction as the audited mutation, so either both are stored or
 * neither is.
 *
 * Callers pass only allowlisted, non-sensitive values in `$changes`; values
 * that must not be stored (page body, secrets) are recorded with
 * {@see self::redacted()}. No IP address or user agent is stored.
 */
class RecordAuditEvent
{
    /**
     * @param  User|null  $actor  Null for console commands and system processes.
     * @param  array<string, array{old?: mixed, new?: mixed, redacted?: bool}>  $changes
     */
    public function handle(AuditAction $action, Model $subject, ?User $actor, array $changes = []): AuditLog
    {
        $subjectKey = $subject->getKey();

        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => is_int($subjectKey) ? $subjectKey : null,
            'changes' => $changes === [] ? null : $changes,
        ]);
    }

    /**
     * A change entry for a non-sensitive value.
     *
     * @return array{old: mixed, new: mixed}
     */
    public static function change(mixed $old, mixed $new): array
    {
        return ['old' => $old, 'new' => $new];
    }

    /**
     * A change entry that records only the fact of the change.
     *
     * @return array{redacted: true}
     */
    public static function redacted(): array
    {
        return ['redacted' => true];
    }
}
