<?php

namespace App\Data\Admin\Redirects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Effective list state echoed back to the redirect list (defaults applied).
 */
#[TypeScript]
class RedirectListFiltersData extends Data
{
    public function __construct(
        public string $search,
        #[LiteralTypeScriptType("'source_path' | 'hits' | 'last_hit_at' | 'updated_at'")]
        public string $sort,
        #[LiteralTypeScriptType("'asc' | 'desc'")]
        public string $direction,
        #[LiteralTypeScriptType("'all' | 'yes' | 'no'")]
        public string $is_active,
    ) {}
}
