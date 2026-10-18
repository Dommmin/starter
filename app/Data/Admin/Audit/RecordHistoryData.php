<?php

namespace App\Data\Admin\Audit;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Newest audit entries of one record, shared as `recordHistory` on its
 * edit screen for users who may read the audit log.
 */
#[TypeScript]
class RecordHistoryData extends Data
{
    /**
     * @param  list<AuditLogListItemData>  $entries  Newest first.
     */
    public function __construct(
        public array $entries,
        public int $limit,
    ) {}
}
