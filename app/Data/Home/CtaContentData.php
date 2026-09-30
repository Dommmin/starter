<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `cta` (call to action) section.
 */
#[TypeScript]
class CtaContentData extends Data
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?HomeActionData $primaryAction = null,
        public ?HomeActionData $secondaryAction = null,
    ) {}
}
