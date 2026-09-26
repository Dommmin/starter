<?php

namespace App\Data\Admin\Faqs;

use App\Models\Faq;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin faq list.
 */
#[TypeScript]
class FaqListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $question,
        public ?int $position,
        public bool $published,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromModel(Faq $faq): self
    {
        return new self(
            id: $faq->id,
            question: $faq->question,
            position: $faq->position,
            published: $faq->published,
            createdAt: $faq->created_at?->toIso8601String(),
            updatedAt: $faq->updated_at?->toIso8601String(),
        );
    }
}
