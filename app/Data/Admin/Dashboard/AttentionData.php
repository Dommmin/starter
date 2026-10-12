<?php

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Things that need someone's action, as counts. A count is null when the
 * user may not open the list where it would be handled.
 */
#[TypeScript]
class AttentionData extends Data
{
    /**
     * @param  int|null  $failedMessages  Contact messages whose delivery failed.
     * @param  int|null  $quarantinedMedia  Files still waiting for the virus scan.
     * @param  int|null  $rejectedMedia  Files the virus scan rejected.
     * @param  int|null  $scheduledSoon  Articles and pages going live in the next `$soonHours` hours.
     * @param  int|null  $staleDrafts  Article drafts untouched for `$staleDays` days.
     * @param  int|null  $adminsWithoutTwoFactor  Administrators without confirmed two-factor authentication.
     */
    public function __construct(
        public ?int $failedMessages,
        public ?int $quarantinedMedia,
        public ?int $rejectedMedia,
        public ?int $scheduledSoon,
        public ?int $staleDrafts,
        public ?int $adminsWithoutTwoFactor,
        public int $soonHours,
        public int $staleDays,
    ) {}
}
