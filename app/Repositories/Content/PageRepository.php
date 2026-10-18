<?php

namespace App\Repositories\Content;

use App\Contracts\Search\AdminSearchSource;
use App\Data\Admin\Dashboard\ContentStatusCountsData;
use App\Data\Admin\Search\AdminSearchResultData;
use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageSlugRedirect;
use App\Models\PageTranslation;
use App\Support\Search\LikeSearch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Named read queries of the Content module for pages.
 */
class PageRepository implements AdminSearchSource
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
     * Find the saved translation of the page in the given locale regardless
     * of its publication state (signed admin preview only).
     */
    public function findForPreview(Page $page, string $locale): ?PageTranslation
    {
        return $page->translations()
            ->where('locale', $locale)
            ->first();
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
     * Published translations of the locale, keyed by page id, optionally
     * limited to the given pages (home page links and their editor options).
     *
     * @param  list<int>|null  $pageIds
     * @return array<int, PageTranslation>
     */
    public function publishedInLocale(string $locale, ?array $pageIds = null): array
    {
        if ($pageIds === []) {
            return [];
        }

        return PageTranslation::query()
            ->published()
            ->where('locale', $locale)
            ->when($pageIds !== null, fn (Builder $query) => $query->whereIn('page_id', $pageIds))
            ->orderBy('title')
            ->get()
            ->keyBy('page_id')
            ->all();
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

    /**
     * Publication state counts of the page translations in one language,
     * computed in a single aggregate query.
     */
    public function statusCounts(string $locale, CarbonInterface $now): ContentStatusCountsData
    {
        $published = PublicationStatus::Published->value;

        /** @var object{published: int|string, drafts: int|string, scheduled: int|string}|null $row */
        $row = PageTranslation::query()
            ->where('locale', $locale)
            ->toBase()
            ->selectRaw('count(case when status = ? and published_at <= ? then 1 end) as published', [$published, $now])
            ->selectRaw('count(case when status = ? then 1 end) as drafts', [PublicationStatus::Draft->value])
            ->selectRaw('count(case when status = ? and (published_at is null or published_at > ?) then 1 end) as scheduled', [$published, $now])
            ->first();

        return new ContentStatusCountsData(
            published: (int) ($row->published ?? 0),
            drafts: (int) ($row->drafts ?? 0),
            scheduled: (int) ($row->scheduled ?? 0),
        );
    }

    /**
     * Pages in one language going live within the next `$hours` hours.
     */
    public function scheduledWithin(string $locale, CarbonInterface $now, int $hours): int
    {
        return PageTranslation::query()
            ->where('locale', $locale)
            ->where('status', PublicationStatus::Published->value)
            ->where('published_at', '>', $now)
            ->where('published_at', '<=', $now->toImmutable()->addHours($hours))
            ->count();
    }

    public function model(): string
    {
        return Page::class;
    }

    /**
     * Pages with a translation whose title or slug contains the term,
     * named by the most recently changed matching translation.
     */
    public function search(string $term, int $limit): array
    {
        $query = PageTranslation::query()->orderByDesc('updated_at')->orderByDesc('id');
        LikeSearch::apply($query, ['title', 'slug'], $term);

        $results = [];
        foreach ($query->limit($limit * 4)->get()->unique('page_id')->take($limit) as $translation) {
            $results[] = new AdminSearchResultData(
                type: 'pages',
                id: $translation->page_id,
                label: $translation->title,
                description: mb_strtoupper($translation->locale).' · '.$translation->slug,
                url: route('admin.pages.edit', $translation->page_id),
            );
        }

        return $results;
    }
}
