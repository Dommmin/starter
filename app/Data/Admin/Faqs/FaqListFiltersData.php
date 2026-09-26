<?php

namespace App\Data\Admin\Faqs;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Effective list state echoed back to the faq list (defaults applied).
 */
#[TypeScript]
class FaqListFiltersData extends Data
{
    public function __construct(
        public string $search,
        #[LiteralTypeScriptType("'question' | 'position' | 'created_at'")]
        public string $sort,
        #[LiteralTypeScriptType("'asc' | 'desc'")]
        public string $direction,
        #[LiteralTypeScriptType("'all' | 'yes' | 'no'")]
        public string $published,
    ) {}
}
