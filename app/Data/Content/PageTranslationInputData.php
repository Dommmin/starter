<?php

namespace App\Data\Content;

use App\Enums\PublicationStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden;

/**
 * Validated input for one page translation, passed from the FormRequest to
 * the page actions. Validation rules live only in the FormRequest.
 */
#[Hidden]
class PageTranslationInputData extends Data
{
    /**
     * @param  array<string, mixed>|null  $body  Tiptap JSON document.
     */
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $metaDescription,
        public ?array $body,
        public PublicationStatus $status,
    ) {}
}
