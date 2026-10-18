<?php

namespace App\Data\Admin\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * JSON response of `admin.search`: records the user may see, grouped by
 * type in a fixed order.
 */
#[TypeScript]
class AdminSearchData extends Data
{
    /**
     * @param  list<AdminSearchResultData>  $results
     */
    public function __construct(
        public string $query,
        public array $results,
    ) {}
}
