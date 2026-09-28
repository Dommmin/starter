<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rendered `faq` section with its questions.
 */
#[TypeScript]
class HomeFaqData extends Data
{
    /**
     * @param  list<HomeFaqItemData>  $items
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public array $items,
    ) {}
}
