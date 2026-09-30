<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered `hero` section; unavailable actions are already dropped.
 */
#[TypeScript]
class HomeHeroData extends Data
{
    public function __construct(
        public ?string $eyebrow,
        public string $title,
        public ?string $description,
        public ?HomeLinkData $primaryAction,
        public ?HomeLinkData $secondaryAction,
    ) {}
}
