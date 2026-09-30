<?php

namespace App\Data\Content;

use App\Data\Media\MediaImageData;
use App\Models\ArticleTranslation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One card of the public article list.
 */
#[TypeScript]
class ArticleSummaryData extends Data
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $excerpt,
        public ?string $publishedAt,
        public ?MediaImageData $cover,
        public string $coverAlt,
    ) {}

    /**
     * Requires `article.cover` to be eager loaded.
     */
    public static function fromTranslation(ArticleTranslation $translation, string $url): self
    {
        $cover = $translation->article->cover;

        return new self(
            title: $translation->title,
            url: $url,
            excerpt: $translation->excerpt,
            publishedAt: $translation->published_at?->toIso8601String(),
            cover: $cover === null ? null : MediaImageData::fromAsset($cover),
            coverAlt: $cover->alt ?? '',
        );
    }
}
