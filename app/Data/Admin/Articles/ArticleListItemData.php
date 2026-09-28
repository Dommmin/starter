<?php

namespace App\Data\Admin\Articles;

use App\Enums\PublicationStatus;
use App\Models\Article;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin article list, described in the list's content locale
 * (falling back to the default public locale when that translation is
 * missing). `scheduled` marks a published translation whose date is ahead.
 */
#[TypeScript]
class ArticleListItemData extends Data
{
    /**
     * @param  list<string>  $locales  Locales that have a translation.
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public PublicationStatus $status,
        public bool $scheduled,
        public ?string $publishedAt,
        public string $locale,
        public array $locales,
        public ?string $updatedAt,
    ) {}

    /**
     * Requires the `translations` relation to be eager loaded.
     */
    public static function fromArticle(Article $article, string $contentLocale, string $defaultLocale): self
    {
        $translation = $article->translation($contentLocale)
            ?? $article->translation($defaultLocale)
            ?? $article->translations->first();

        $locales = [];
        foreach ($article->translations as $articleTranslation) {
            $locales[] = $articleTranslation->locale;
        }
        sort($locales);

        $status = $translation->status ?? PublicationStatus::Draft;
        $publishedAt = $translation?->published_at;

        return new self(
            id: $article->id,
            title: $translation->title ?? '',
            slug: $translation->slug ?? '',
            status: $status,
            scheduled: $status === PublicationStatus::Published && $publishedAt !== null && $publishedAt->isFuture(),
            publishedAt: $publishedAt?->toIso8601String(),
            locale: $translation->locale ?? $contentLocale,
            locales: $locales,
            updatedAt: $article->updated_at?->toIso8601String(),
        );
    }
}
