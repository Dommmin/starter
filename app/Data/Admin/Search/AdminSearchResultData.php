<?php

namespace App\Data\Admin\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One record found by the admin command palette. `type` selects the group
 * heading and icon; `url` opens the record in the panel.
 */
#[TypeScript]
class AdminSearchResultData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType("'pages' | 'articles' | 'tags' | 'faqs' | 'media' | 'users'")]
        public string $type,
        public int $id,
        public string $label,
        public ?string $description,
        public string $url,
    ) {}
}
