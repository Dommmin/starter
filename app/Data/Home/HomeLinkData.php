<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A home page button with its URL already resolved for the page locale.
 */
#[TypeScript]
class HomeLinkData extends Data
{
    public function __construct(
        public string $label,
        public string $url,
    ) {}
}
