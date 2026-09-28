<?php

namespace App\Services\Content;

use App\Models\ArticleSlugRedirect;
use App\Models\ArticleTranslation;

/**
 * Maintains permanent redirects from former article slugs (per locale),
 * with the same rules as PageSlugRedirects: redirects target the
 * translation (no chains) and a slug in use always wins over a redirect.
 */
class ArticleSlugRedirects
{
    /**
     * Remove a redirect that the given slug now takes over.
     */
    public function claim(string $locale, string $slug): void
    {
        ArticleSlugRedirect::query()
            ->where('locale', $locale)
            ->where('old_slug', $slug)
            ->delete();
    }

    /**
     * Remember the former slug of a translation that has ever been public.
     */
    public function rememberFormerSlug(ArticleTranslation $translation, string $oldSlug): void
    {
        ArticleSlugRedirect::query()->updateOrCreate(
            ['locale' => $translation->locale, 'old_slug' => $oldSlug],
            ['article_translation_id' => $translation->id],
        );
    }
}
