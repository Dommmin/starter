<?php

namespace App\Data\Listing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One record of a hasMany relation shown on the parent's edit screen.
 */
#[TypeScript]
class RelatedRecordData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public string $url,
    ) {}
}
