<?php

namespace App\Repositories\Content;

use App\Models\Page;
use App\Models\PageSlugRedirect;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Named read queries of the Content module for pages.
 */
class PageRepository
{
    /**
     * Base query of the admin page list. The list definition in
     * ListPagesRequest joins the translation of the selected content locale.
     *
     * @return Builder<Page>
     */
    public function adminListQuery(): Builder
    {
        return Page::query()
            ->select('pages.*')
            ->with('translations');
    }

    /**
     * Load a page with every translation for the editor.
     */
    public function forEditor(Page $page): Page
    {
        return $page->load('translations');
    }

    /**
     * Find the translation visitors may see at the given locale and slug,
     * with its sibling translations for language alternates.
     */
    public function findPublishedTranslation(string $locale, string $slug): ?PageTranslation
    {
        return PageTranslation::query()
            ->published()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with(['page.translations' => fn ($query) => $query->published()])
            ->first();
    }

    /**
     * Find the published translation a former slug redirects to in the given
     * locale. A redirect to a draft resolves to null (the old URL is a 404).
     */
    public function findPublishedRedirectTarget(string $locale, string $oldSlug): ?PageTranslation
    {
        return PageTranslation::query()
            ->published()
            ->where('locale', $locale)
            ->whereIn('id', PageSlugRedirect::query()
                ->select('page_translation_id')
                ->where('locale', $locale)
                ->where('old_slug', $oldSlug))
            ->first();
    }

    /**
     * Pages with at least one published translation, each with only its
     * published translations loaded. Drafts never reach the sitemap.
     *
     * @return Collection<int, Page>
     */
    public function publishedForSitemap(): Collection
    {
        return Page::query()
            ->whereHas('translations', function (Builder $query): void {
                /** @var Builder<PageTranslation> $query */
                $query->published();
            })
            ->with(['translations' => fn ($query) => $query->published()->orderBy('locale')])
            ->orderBy('id')
            ->get();
    }
}
