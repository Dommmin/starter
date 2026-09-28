<?php

namespace App\Data\Home;

use App\Enums\HomeIcon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One item of the `features` section.
 */
#[TypeScript]
class FeatureItemData extends Data
{
    public function __construct(
        public string $title,
        public string $description,
        public ?HomeIcon $icon = null,
    ) {}
}
