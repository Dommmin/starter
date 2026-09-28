<?php

namespace App\Data\Admin\Articles;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Effective list state echoed back to the article list (defaults applied).
 */
#[TypeScript]
class ArticleListFiltersData extends Data
{
    public function __construct(
        public string $search,
        #[LiteralTypeScriptType("'title' | 'updated_at'")]
        public string $sort,
        #[LiteralTypeScriptType("'asc' | 'desc'")]
        public string $direction,
        #[LiteralTypeScriptType("'all' | 'draft' | 'published'")]
        public string $status,
        public string $locale,
    ) {}
}
