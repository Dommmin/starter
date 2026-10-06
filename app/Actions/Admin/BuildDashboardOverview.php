<?php

namespace App\Actions\Admin;

use App\Data\Admin\Audit\AuditLogListItemData;
use App\Data\Admin\Dashboard\DashboardOverviewData;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\User;
use App\Repositories\Audit\AuditLogRepository;
use App\Repositories\Contact\ContactMessageRepository;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Repositories\Media\MediaAssetRepository;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationConfig;
use Carbon\CarbonImmutable;

/**
 * Collect the content overview of the panel dashboard. A block is computed
 * only when the user may open the matching list, so the dashboard never
 * reveals more than the navigation does.
 */
class BuildDashboardOverview
{
    public const int RECENT_CONTACT_DAYS = 7;

    public const int RECENT_ACTIVITY_LIMIT = 5;

    public function __construct(
        private readonly LocalizationConfig $localization,
        private readonly SiteSettingsRepository $siteSettings,
        private readonly ArticleRepository $articles,
        private readonly PageRepository $pages,
        private readonly ContactMessageRepository $contactMessages,
        private readonly MediaAssetRepository $media,
        private readonly AuditLogRepository $auditLogs,
    ) {}

    public function handle(User $user): DashboardOverviewData
    {
        $locale = $this->localization->getPublicDefault();
        $now = CarbonImmutable::now();

        return new DashboardOverviewData(
            siteName: $this->siteSettings->current($locale)->name,
            contentLocale: $locale,
            articles: $user->can('viewAny', Article::class)
                ? $this->articles->statusCounts($locale, $now)
                : null,
            pages: $user->can('viewAny', Page::class)
                ? $this->pages->statusCounts($locale, $now)
                : null,
            contact: $user->can('viewAny', ContactMessage::class)
                ? $this->contactMessages->dashboardCounts($now, self::RECENT_CONTACT_DAYS)
                : null,
            quarantinedMedia: $user->can('viewAny', MediaAsset::class)
                ? $this->media->quarantinedCount()
                : null,
            recentActivity: $user->can('viewAny', AuditLog::class)
                ? array_values($this->auditLogs->latest(self::RECENT_ACTIVITY_LIMIT)
                    ->map(AuditLogListItemData::fromAuditLog(...))
                    ->all())
                : null,
        );
    }
}
