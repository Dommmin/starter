<?php

namespace App\Data\Home;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `portfolio` section (at most MAX_ITEMS projects).
 */
#[TypeScript]
class PortfolioContentData extends Data
{
    public const int MAX_ITEMS = 12;

    /**
     * @param  list<PortfolioItemData>  $items
     */
    public function __construct(
        #[DataCollectionOf(PortfolioItemData::class)]
        public array $items = [],
        public ?string $title = null,
        public ?string $description = null,
    ) {}
}
