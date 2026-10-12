<?php

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Contact form messages received on one day, split by delivery outcome.
 */
#[TypeScript]
class ContactDayData extends Data
{
    /**
     * @param  string  $date  Day in the application time zone, `Y-m-d`.
     * @param  int  $undelivered  Messages still pending or whose delivery failed.
     */
    public function __construct(
        public string $date,
        public int $delivered,
        public int $undelivered,
    ) {}
}
