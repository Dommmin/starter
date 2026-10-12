<?php

namespace App\Data\Consent;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Plausible analytics script loaded after the analytics consent.
 */
#[TypeScript]
class PlausibleData extends Data
{
    public function __construct(
        public string $domain,
        public string $scriptUrl,
    ) {}
}
