<?php

namespace App\Repositories\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Named read queries of the audit log.
 */
class AuditLogRepository
{
    /**
     * Base query of the admin audit list with the actor's display name.
     *
     * @return Builder<AuditLog>
     */
    public function adminListQuery(): Builder
    {
        return AuditLog::query()->with('actor:id,name');
    }
}
