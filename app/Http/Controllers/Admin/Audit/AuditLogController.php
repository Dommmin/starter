<?php

namespace App\Http\Controllers\Admin\Audit;

use App\Data\Admin\Audit\AuditLogIndexData;
use App\Data\Admin\Audit\AuditLogListFiltersData;
use App\Data\Admin\Audit\AuditLogListItemData;
use App\Data\Listing\ListPaginationData;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Audit\ListAuditLogsRequest;
use App\Repositories\Audit\AuditLogRepository;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only audit log for administrators (`can:viewAny` on the route).
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogRepository $auditLogs) {}

    /**
     * Display the filterable, paginated audit log, newest first by default.
     */
    public function index(ListAuditLogsRequest $request): Response
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();

        $paginator = $listQuery->paginate($this->auditLogs->adminListQuery(), $validated);

        $items = [];
        foreach ($paginator->items() as $log) {
            $items[] = AuditLogListItemData::fromAuditLog($log);
        }

        return Inertia::render('admin/audit/index', new AuditLogIndexData(
            items: $items,
            pagination: ListPaginationData::from($listQuery->paginationPayload($paginator)),
            filters: AuditLogListFiltersData::from($listQuery->filtersPayload($validated)),
            actions: AuditAction::cases(),
        ));
    }
}
