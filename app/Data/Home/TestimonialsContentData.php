<?php

namespace App\Data\Home;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `testimonials` section (at most MAX_ITEMS quotes).
 */
#[TypeScript]
class TestimonialsContentData extends Data
{
    public const int MAX_ITEMS = 6;

    /**
     * @param  list<TestimonialItemData>  $items
     */
    public function __construct(
        #[DataCollectionOf(TestimonialItemData::class)]
        public array $items = [],
        public ?string $title = null,
    ) {}
}
