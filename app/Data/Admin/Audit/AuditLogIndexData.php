<?php

namespace App\Data\Admin\Audit;

use App\Data\Listing\ListPaginationData;
use App\Enums\AuditAction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the read-only `admin/audit/index` screen.
 */
#[TypeScript]
class AuditLogIndexData extends Data
{
    /**
     * @param  list<AuditLogListItemData>  $items
     * @param  list<AuditAction>  $actions  Options of the action filter.
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
        public AuditLogListFiltersData $filters,
        public array $actions,
    ) {}
}
