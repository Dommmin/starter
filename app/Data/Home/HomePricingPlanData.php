<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered plan of the `pricing` section; an unavailable action is dropped.
 */
#[TypeScript]
class HomePricingPlanData extends Data
{
    /**
     * @param  list<string>  $features
     */
    public function __construct(
        public string $name,
        public ?int $priceMinor,
        public string $currency,
        public bool $priceFrom,
        public ?string $period,
        public ?string $description,
        public array $features,
        public bool $highlighted,
        public ?HomeLinkData $action,
    ) {}
}
