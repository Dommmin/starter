<?php

namespace App\Data\Admin\Media;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Effective list state echoed back to the media list (defaults applied).
 */
#[TypeScript]
class MediaAssetListFiltersData extends Data
{
    public function __construct(
        public string $search,
        #[LiteralTypeScriptType("'original_name' | 'size' | 'created_at'")]
        public string $sort,
        #[LiteralTypeScriptType("'asc' | 'desc'")]
        public string $direction,
        #[LiteralTypeScriptType("'all' | 'quarantine' | 'clean' | 'rejected'")]
        public string $status,
        #[LiteralTypeScriptType("'all' | 'image' | 'document'")]
        public string $type,
    ) {}
}
