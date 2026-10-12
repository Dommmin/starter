<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One project of the `portfolio` section: an optional DAM thumbnail (asset
 * id), a free-text category and an optional external link to the project.
 */
#[TypeScript]
class PortfolioItemData extends Data
{
    public function __construct(
        public string $title,
        public ?string $category = null,
        public ?string $description = null,
        public ?int $imageId = null,
        public ?string $url = null,
    ) {}
}
