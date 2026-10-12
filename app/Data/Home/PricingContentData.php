<?php

namespace App\Data\Home;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `pricing` section (at most MAX_ITEMS plans) with
 * an optional note under the plans, e.g. "Prices are net".
 */
#[TypeScript]
class PricingContentData extends Data
{
    public const int MAX_ITEMS = 4;

    /** Currencies a plan may be priced in (ISO 4217). */
    public const array CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP', 'CHF'];

    /**
     * @param  list<PricingPlanData>  $items
     */
    public function __construct(
        #[DataCollectionOf(PricingPlanData::class)]
        public array $items = [],
        public ?string $title = null,
        public ?string $description = null,
        public ?string $note = null,
    ) {}
}
