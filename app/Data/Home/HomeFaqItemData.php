<?php

namespace App\Data\Home;

use App\Models\Faq;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One published question of the rendered `faq` section (plain text).
 */
#[TypeScript]
class HomeFaqItemData extends Data
{
    public function __construct(
        public int $id,
        public string $question,
        public string $answer,
    ) {}

    public static function fromModel(Faq $faq): self
    {
        return new self(id: $faq->id, question: $faq->question, answer: $faq->answer);
    }
}
