<?php

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Article publishing pace for the dashboard, in the default public language.
 */
#[TypeScript]
class PublishingData extends Data
{
    /**
     * @param  int  $recent  Articles published in the last `$days` days.
     * @param  int  $previous  Articles published in the `$days` days before that.
     * @param  string|null  $nextScheduledAt  Publication time (ISO 8601) of the next scheduled article.
     */
    public function __construct(
        public int $recent,
        public int $previous,
        public int $days,
        public ?string $nextScheduledAt,
    ) {}
}
