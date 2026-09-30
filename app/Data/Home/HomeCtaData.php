<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered `cta` section; unavailable actions are already dropped.
 */
#[TypeScript]
class HomeCtaData extends Data
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?HomeLinkData $primaryAction,
        public ?HomeLinkData $secondaryAction,
    ) {}
}
