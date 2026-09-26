<?php

namespace App\Data\Admin\Audit;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Effective list state echoed back to the audit list (defaults applied).
 * The list has no free-text search; `search` is always empty and kept for
 * the uniform ResourceTable contract.
 */
#[TypeScript]
class AuditLogListFiltersData extends Data
{
    public function __construct(
        public string $search,
        #[LiteralTypeScriptType("'created_at'")]
        public string $sort,
        #[LiteralTypeScriptType("'asc' | 'desc'")]
        public string $direction,
        #[LiteralTypeScriptType("'all' | App.Enums.AuditAction")]
        public string $action,
    ) {}
}
