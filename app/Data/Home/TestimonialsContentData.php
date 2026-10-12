<?php

namespace App\Data\Home;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `testimonials` section: at most MAX_ITEMS quotes and
 * at most MAX_STATS figures shown beside them as proof (e.g. an average
 * rating or the reply time).
 */
#[TypeScript]
class TestimonialsContentData extends Data
{
    public const int MAX_ITEMS = 6;

    public const int MAX_STATS = HeroContentData::MAX_STATS;

    /**
     * @param  list<TestimonialItemData>  $items
     * @param  list<HeroStatData>  $stats
     */
    public function __construct(
        #[DataCollectionOf(TestimonialItemData::class)]
        public array $items = [],
        public ?string $title = null,
        #[DataCollectionOf(HeroStatData::class)]
        public array $stats = [],
    ) {}
}
