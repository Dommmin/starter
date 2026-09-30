<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `hero` section.
 */
#[TypeScript]
class HeroContentData extends Data
{
    public function __construct(
        public string $title,
        public ?string $eyebrow = null,
        public ?string $description = null,
        public ?HomeActionData $primaryAction = null,
        public ?HomeActionData $secondaryAction = null,
    ) {}
}
