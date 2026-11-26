<?php

namespace App\Data\Admin\Faqs;

use App\Models\Faq;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One faq of the shared order list; `locale` null means every language.
 */
#[TypeScript]
class FaqOrderItemData extends Data
{
    public function __construct(
        public int $id,
        public string $question,
        public ?string $locale,
        public bool $published,
    ) {}

    public static function fromModel(Faq $faq): self
    {
        return new self(
            id: $faq->id,
            question: $faq->question,
            locale: $faq->locale,
            published: $faq->published,
        );
    }
}
