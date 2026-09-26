<?php

namespace App\Data\Seo;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Publisher of the site for the Organization JSON-LD node. URLs are absolute.
 */
#[TypeScript]
class SeoOrganizationData extends Data
{
    public function __construct(
        public string $name,
        public string $url,
        public ?string $logo,
    ) {}
}
