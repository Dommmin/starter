<?php

namespace App\Data\Home;

use App\Data\Media\MediaImageData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered project of the `portfolio` section; a thumbnail that is no
 * longer a usable DAM image is null.
 */
#[TypeScript]
class HomePortfolioItemData extends Data
{
    public function __construct(
        public string $title,
        public ?string $category,
        public ?string $description,
        public ?MediaImageData $image,
        public string $imageAlt,
        public ?string $url,
    ) {}
}
