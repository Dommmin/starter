<?php

namespace App\Data\Admin\Articles;

use App\Models\Article;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state for creating or editing an article. `translations` always
 * contains every active public locale; `updatedAt` must be sent back on
 * update for optimistic locking.
 */
#[TypeScript]
class ArticleFormData extends Data
{
    /**
     * @param  array<string, ArticleTranslationFormData>  $translations
     */
    public function __construct(
        public ?int $id,
        public ?string $updatedAt,
        public ?int $coverMediaId,
        public array $translations,
    ) {}

    /**
     * @param  list<string>  $locales
     */
    public static function blank(array $locales): self
    {
        $translations = [];

        foreach ($locales as $locale) {
            $translations[$locale] = ArticleTranslationFormData::blank();
        }

        return new self(id: null, updatedAt: null, coverMediaId: null, translations: $translations);
    }

    /**
     * Requires the `translations` relation to be eager loaded.
     *
     * @param  list<string>  $locales
     */
    public static function fromArticle(Article $article, array $locales): self
    {
        $translations = [];

        foreach ($locales as $locale) {
            $translation = $article->translation($locale);

            $translations[$locale] = $translation === null
                ? ArticleTranslationFormData::blank()
                : ArticleTranslationFormData::fromTranslation($translation);
        }

        return new self(
            id: $article->id,
            updatedAt: $article->updated_at?->toIso8601String(),
            coverMediaId: $article->cover_media_id,
            translations: $translations,
        );
    }
}
