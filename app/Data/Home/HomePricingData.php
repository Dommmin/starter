<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered `pricing` section with plan actions resolved for the locale.
 */
#[TypeScript]
class HomePricingData extends Data
{
    /**
     * @param  list<HomePricingPlanData>  $items
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public array $items,
        public ?string $note,
    ) {}
}
