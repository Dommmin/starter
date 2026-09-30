<?php

namespace App\Data\Home;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored content of the `faq` section. Questions come from published FAQs
 * of the page locale or of every locale (`faqs.locale` null); `limit` null
 * shows all of them.
 */
#[TypeScript]
class FaqContentData extends Data
{
    public const int MAX_LIMIT = 50;

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?int $limit = null,
    ) {}
}
