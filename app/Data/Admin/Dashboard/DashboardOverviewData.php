<?php

namespace App\Data\Admin\Dashboard;

use App\Data\Admin\Audit\AuditLogListItemData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Content overview shown at the top of the panel dashboard. Every block is
 * null when the current user may not open the matching list.
 */
#[TypeScript]
class DashboardOverviewData extends Data
{
    /**
     * @param  string  $contentLocale  Public language the content counts refer to.
     * @param  list<AuditLogListItemData>|null  $recentActivity  Newest audit entries.
     */
    public function __construct(
        public string $siteName,
        public string $contentLocale,
        public ?ContentStatusCountsData $articles,
        public ?ContentStatusCountsData $pages,
        public ?ContactCountsData $contact,
        public ?int $quarantinedMedia,
        public ?array $recentActivity,
    ) {}
}
