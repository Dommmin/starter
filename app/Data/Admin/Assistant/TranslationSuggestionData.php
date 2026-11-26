<?php

namespace App\Data\Admin\Assistant;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * JSON response of `admin.assistant.translation`: a draft of one language
 * version the admin reads, edits and saves in the form. Fields empty in the
 * source are empty here as well.
 */
#[TypeScript]
class TranslationSuggestionData extends Data
{
    /**
     * @param  string|null  $excerpt  Null for content types without an excerpt (pages).
     * @param  array<string, mixed>|null  $body  The source Tiptap document with only its text nodes translated; null without a source body.
     */
    public function __construct(
        public string $title,
        public ?string $excerpt,
        public string $metaDescription,
        public ?array $body,
    ) {}
}
