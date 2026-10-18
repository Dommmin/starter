<?php

namespace App\Actions\Audit;

use App\Data\Admin\Audit\AuditLogListItemData;
use App\Data\Admin\Audit\RecordHistoryData;
use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\Audit\AuditLogRepository;
use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;

/**
 * Share the newest audit entries of the edited record as the
 * `recordHistory` page prop, only for users who may read the audit log
 * (the timeline shows who changed which fields, not the values).
 */
class ShareRecordHistory
{
    public const int LIMIT = 10;

    public function __construct(private readonly AuditLogRepository $auditLogs) {}

    public function handle(?User $user, Model $subject): void
    {
        if ($user === null || ! $user->can('viewAny', AuditLog::class)) {
            return;
        }

        Inertia::share('recordHistory', new RecordHistoryData(
            entries: array_values($this->auditLogs->forSubject($subject, self::LIMIT)
                ->map(AuditLogListItemData::fromAuditLog(...))
                ->all()),
            limit: self::LIMIT,
        ));
    }
}
