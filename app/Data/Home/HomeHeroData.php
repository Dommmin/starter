<?php

namespace App\Data\Home;

use App\Data\Media\MediaImageData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered `hero` section; unavailable actions are already dropped and an
 * image that is no longer a usable DAM image is null.
 */
#[TypeScript]
class HomeHeroData extends Data
{
    /**
     * @param  list<HeroStatData>  $stats
     */
    public function __construct(
        public ?string $eyebrow,
        public string $title,
        public ?string $description,
        public ?HomeLinkData $primaryAction,
        public ?HomeLinkData $secondaryAction,
        public ?MediaImageData $image,
        public string $imageAlt,
        public array $stats,
    ) {}
}
