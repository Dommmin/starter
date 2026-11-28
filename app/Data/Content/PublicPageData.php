<?php

namespace App\Data\Content;

use App\Data\Contact\ContactFormData;
use App\Data\Home\HomeSectionData;
use App\Models\PageTranslation;
use App\Services\Content\RichTextRenderer;
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
     * @param  list<HomeSectionData>|null  $sections
     */
    public function __construct(
        public string $title,
        public ?string $metaDescription,
        public string $bodyHtml,
        public string $locale,
        public ?string $publishedAt,
        public array $alternates,
        public ?ContentPreviewData $preview = null,
        public string $layoutMode = 'rich_text',
        public ?array $sections = null,
        public ?ContactFormData $contactForm = null,
    ) {}

    /**
     * @param  array<string, string>  $alternates
     * @param  list<HomeSectionData>|null  $sections
     */
    public static function fromTranslation(
        PageTranslation $translation,
        string $bodyHtml,
        array $alternates,
        ?ContentPreviewData $preview = null,
        string $layoutMode = 'rich_text',
        ?array $sections = null,
        ?ContactFormData $contactForm = null,
    ): self {
        return new self(
            title: $translation->title,
            metaDescription: $translation->meta_description ?? RichTextRenderer::summary($translation->body),
            bodyHtml: $bodyHtml,
            locale: $translation->locale,
            publishedAt: $translation->published_at?->toIso8601String(),
            alternates: $alternates,
            preview: $preview,
            layoutMode: $layoutMode,
            sections: $sections,
            contactForm: $contactForm,
        );
    }
}
