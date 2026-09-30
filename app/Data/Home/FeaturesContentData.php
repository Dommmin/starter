<?php

namespace App\Data\Home;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `features` section (at most MAX_ITEMS items).
 */
#[TypeScript]
class FeaturesContentData extends Data
{
    public const int MAX_ITEMS = 12;

    /**
     * @param  list<FeatureItemData>  $items
     */
    public function __construct(
        #[DataCollectionOf(FeatureItemData::class)]
        public array $items = [],
        public ?string $title = null,
        public ?string $description = null,
    ) {}
}
