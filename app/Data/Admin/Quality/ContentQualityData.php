<?php

namespace App\Data\Admin\Quality;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContentQualityData extends Data
{
    /**
     * @param  list<ContentIssueData>  $issues  Most urgent types first.
     * @param  int  $total  All found issues, also those over the limit.
     */
    public function __construct(
        public array $issues,
        public int $total,
    ) {}
}
