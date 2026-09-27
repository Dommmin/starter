<?php

namespace App\Rules;

use App\Services\Content\RichTextRenderer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accept only Tiptap documents that fit the closed rich text schema.
 * Unknown nodes, marks, unsafe links, images that are not clean DAM images
 * and oversized documents are rejected.
 */
class RichTextDocument implements ValidationRule
{
    public function __construct(private readonly RichTextRenderer $renderer) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $violations = $this->renderer->violations($value);

        if ($violations === []) {
            return;
        }

        $fail(match ($violations[0]) {
            'too_large' => __('admin.pages.validation.bodyTooLarge'),
            'invalid_link' => __('admin.pages.validation.bodyInvalidLink'),
            'invalid_image' => __('admin.richText.imageInvalid'),
            default => __('admin.pages.validation.bodyInvalid'),
        });
    }
}
