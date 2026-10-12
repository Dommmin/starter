<?php

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Contact form inbox summary for the dashboard.
 */
#[TypeScript]
class ContactCountsData extends Data
{
    /**
     * @param  int  $recent  Messages received in the last `$recentDays` days.
     * @param  int  $previous  Messages received in the `$recentDays` days before that.
     * @param  int  $failed  Messages whose delivery failed and may need a retry.
     * @param  list<ContactDayData>  $daily  One entry per day of the last `$chartDays` days, oldest first.
     */
    public function __construct(
        public int $recent,
        public int $failed,
        public int $recentDays,
        public int $previous = 0,
        public array $daily = [],
        public int $chartDays = 0,
    ) {}
}
