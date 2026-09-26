<?php

namespace App\Services\Content;

use App\Models\PageSlugRedirect;
use App\Models\PageTranslation;

/**
 * Maintains permanent redirects from former page slugs (per locale).
 *
 * Rules, applied inside the caller's transaction:
 * - A redirect targets the translation, never another slug, so repeated slug
 *   changes (A → B → C) never form a chain: A and B both lead to C directly.
 * - A slug in use by a translation always wins over a redirect: saving a
 *   translation with a slug removes any redirect with that slug in the same
 *   locale, whether it belonged to the same page (returning to a former
 *   slug, which would otherwise loop) or to another page.
 */
class PageSlugRedirects
{
    /**
     * Remove a redirect that the given slug now takes over.
     */
    public function claim(string $locale, string $slug): void
    {
        PageSlugRedirect::query()
            ->where('locale', $locale)
            ->where('old_slug', $slug)
            ->delete();
    }

    /**
     * Remember the former slug of a translation that has ever been public.
     * Drafts that were never published keep no redirect: their old URL was
     * never visible to visitors or search engines.
     */
    public function rememberFormerSlug(PageTranslation $translation, string $oldSlug): void
    {
        PageSlugRedirect::query()->updateOrCreate(
            ['locale' => $translation->locale, 'old_slug' => $oldSlug],
            ['page_translation_id' => $translation->id],
        );
    }
}
