<?php

namespace App\Data\Admin\Assistant;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * JSON response of `admin.assistant.seo-meta`: a proposal the admin may
 * copy into the translation form.
 */
#[TypeScript]
class SeoMetaSuggestionData extends Data
{
    public function __construct(
        public string $metaDescription,
        public string $slug,
    ) {}
}
