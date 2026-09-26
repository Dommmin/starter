<?php

namespace App\Data\Errors;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `errors/show` screen rendered for HTTP errors.
 */
#[TypeScript]
class ErrorPageData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('403 | 404 | 500 | 503')]
        public int $status,
    ) {}
}
