<?php

namespace App\Data\Content;

use App\Data\Media\MediaImageData;
use App\Models\ArticleTranslation;
use App\Services\Content\RichTextRenderer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `articles/show` screen. `bodyHtml` is produced by
 * RichTextRenderer from the closed schema and is safe to inject. `preview`
 * is set only by the signed admin preview.
 */
#[TypeScript]
class PublicArticleData extends Data
{
    /**
     * @param  array<string, string>  $alternates  Locale (and `x-default`) => canonical URL of each visible translation.
     */
    public function __construct(
        public string $title,
        public ?string $excerpt,
        public ?string $metaDescription,
        public string $bodyHtml,
        public string $locale,
        public ?string $publishedAt,
        public ?string $updatedAt,
        public ?MediaImageData $cover,
        public string $coverAlt,
        public string $listUrl,
        public array $alternates,
        public ?ContentPreviewData $preview = null,
    ) {}

    /**
     * Requires the `article.cover` relation to be eager loaded.
     *
     * @param  array<string, string>  $alternates
     */
    public static function fromTranslation(
        ArticleTranslation $translation,
        string $bodyHtml,
        string $listUrl,
        array $alternates,
        ?ContentPreviewData $preview = null,
    ): self {
        $cover = $translation->article->cover;

        return new self(
            title: $translation->title,
            excerpt: $translation->excerpt,
            metaDescription: $translation->meta_description
                ?? $translation->excerpt
                ?? RichTextRenderer::summary($translation->body),
            bodyHtml: $bodyHtml,
            locale: $translation->locale,
            publishedAt: $translation->published_at?->toIso8601String(),
            updatedAt: $translation->updated_at?->toIso8601String(),
            cover: $cover === null ? null : MediaImageData::fromAsset($cover),
            coverAlt: $cover->alt ?? '',
            listUrl: $listUrl,
            alternates: $alternates,
            preview: $preview,
        );
    }
}
