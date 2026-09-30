<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One quote of the `testimonials` section.
 */
#[TypeScript]
class TestimonialItemData extends Data
{
    public function __construct(
        public string $author,
        public string $quote,
        public ?string $role = null,
    ) {}
}
