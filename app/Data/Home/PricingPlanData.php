<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One plan of the `pricing` section. The price is an integer in the
 * smallest currency unit with an ISO 4217 code; null means a quote on
 * request, `priceFrom` marks a starting price. At most MAX_FEATURES
 * short scope entries.
 */
#[TypeScript]
class PricingPlanData extends Data
{
    public const int MAX_FEATURES = 10;

    /**
     * @param  list<string>  $features
     */
    public function __construct(
        public string $name,
        public ?int $priceMinor = null,
        public string $currency = 'PLN',
        public bool $priceFrom = false,
        public ?string $period = null,
        public ?string $description = null,
        public array $features = [],
        public bool $highlighted = false,
        public ?HomeActionData $action = null,
    ) {}
}
