<?php

namespace App\Data\Content;

use App\Models\PageTranslation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `pages/show` screen. `bodyHtml` is produced by
 * RichTextRenderer from the closed schema and is safe to inject. `preview`
 * is set only by the signed admin preview.
 */
#[TypeScript]
class PublicPageData extends Data
{
    /**
     * @param  array<string, string>  $alternates  Locale (and `x-default`) => canonical URL of each published translation.
     */
    public function __construct(
        public string $title,
        public ?string $metaDescription,
        public string $bodyHtml,
        public string $locale,
        public ?string $publishedAt,
        public array $alternates,
        public ?ContentPreviewData $preview = null,
    ) {}

    /**
     * @param  array<string, string>  $alternates
     */
    public static function fromTranslation(
        PageTranslation $translation,
        string $bodyHtml,
        array $alternates,
        ?ContentPreviewData $preview = null,
    ): self {
        return new self(
            title: $translation->title,
            metaDescription: $translation->meta_description,
            bodyHtml: $bodyHtml,
            locale: $translation->locale,
            publishedAt: $translation->published_at?->toIso8601String(),
            alternates: $alternates,
            preview: $preview,
        );
    }
}
