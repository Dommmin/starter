<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered `portfolio` section with its thumbnails resolved.
 */
#[TypeScript]
class HomePortfolioData extends Data
{
    /**
     * @param  list<HomePortfolioItemData>  $items
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public array $items,
    ) {}
}
