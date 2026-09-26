<?php

namespace App\Data\Content;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One active public locale that content can be written in.
 */
#[TypeScript]
class ContentLocaleData extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public string $native,
        #[LiteralTypeScriptType("'ltr' | 'rtl'")]
        public string $dir,
    ) {}
}
