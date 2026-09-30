<?php

namespace App\Data\Admin\Faqs;

use App\Models\Faq;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state for creating or editing a faq. `updatedAt` must be
 * sent back on update for optimistic locking. `locale` null means the
 * question is shown in every public language.
 */
#[TypeScript]
class FaqFormData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $updatedAt,
        public ?string $question,
        public ?string $answer,
        public ?string $locale,
        public ?int $position,
        public bool $published,
    ) {}

    public static function blank(): self
    {
        return new self(
            id: null,
            updatedAt: null,
            question: null,
            answer: null,
            locale: null,
            position: null,
            published: false,
        );
    }

    public static function fromModel(Faq $faq): self
    {
        return new self(
            id: $faq->id,
            updatedAt: $faq->updated_at?->toIso8601String(),
            question: $faq->question,
            answer: $faq->answer,
            locale: $faq->locale,
            position: $faq->position,
            published: $faq->published,
        );
    }
}
