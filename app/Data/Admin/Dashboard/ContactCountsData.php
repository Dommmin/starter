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
     * @param  int  $failed  Messages whose delivery failed and may need a retry.
     */
    public function __construct(
        public int $recent,
        public int $failed,
        public int $recentDays,
    ) {}
}
