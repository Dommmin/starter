<?php

namespace App\Data\Admin\Assistant;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One proposed FAQ entry, cut to the column limits.
 */
#[TypeScript]
class FaqSuggestionItemData extends Data
{
    public function __construct(
        public string $question,
        public string $answer,
    ) {}
}
