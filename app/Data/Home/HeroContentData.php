<?php

namespace App\Data\Home;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `hero` section: copy, actions, an optional DAM
 * image (asset id) and at most MAX_STATS figures.
 */
#[TypeScript]
class HeroContentData extends Data
{
    public const int MAX_STATS = 4;

    /**
     * @param  list<HeroStatData>  $stats
     */
    public function __construct(
        public string $title,
        public ?string $eyebrow = null,
        public ?string $description = null,
        public ?HomeActionData $primaryAction = null,
        public ?HomeActionData $secondaryAction = null,
        public ?int $imageId = null,
        #[DataCollectionOf(HeroStatData::class)]
        public array $stats = [],
    ) {}
}
