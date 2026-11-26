<?php

namespace App\Data\Admin\Assistant;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * JSON response of `admin.assistant.text-rewrite`: a rewritten text the
 * admin may put in place of the original.
 */
#[TypeScript]
class TextRewriteSuggestionData extends Data
{
    public function __construct(
        public string $text,
    ) {}
}
