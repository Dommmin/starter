<?php

namespace App\Repositories\Navigation;

use App\Enums\MenuLocation;
use App\Enums\PublicationStatus;
use App\Models\ArticleTranslation;
use App\Models\MenuItem;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Queries of the navigation menus: the public resolution, the admin tree
 * and the target/parent options of the editor.
 */
class MenuItemRepository
{
    /**
     * Every item of one menu, parents first and siblings in their order,
     * with the target translations in the menu locale that may be visible:
     * published page translations and published article translations
     * (including scheduled ones, which the caller filters by date).
     *
     * @return Collection<int, MenuItem>
     */
    public function publicMenu(MenuLocation $location, string $locale): Collection
    {
        return $this->menuQuery($location, $locale)
            ->with([
                'pageTranslations' => fn (Relation $query) => $query
                    ->where('locale', $locale)
                    ->where('status', PublicationStatus::Published->value)
                    ->select(['id', 'page_id', 'locale', 'title', 'slug', 'status']),
                'articleTranslations' => fn (Relation $query) => $query
                    ->where('locale', $locale)
                    ->where('status', PublicationStatus::Published->value)
                    ->whereNotNull('published_at')
                    ->select(['id', 'article_id', 'locale', 'title', 'slug', 'status', 'published_at']),
            ])
            ->get();
    }

    /**
     * The admin tree of one menu: first-level items with their children,
     * each with the target translation in the menu locale in any status.
     *
     * @return Collection<int, MenuItem>
     */
    public function adminTree(MenuLocation $location, string $locale): Collection
    {
        $translations = $this->targetTranslations($locale);

        return $this->menuQuery($location, $locale)
            ->whereNull('parent_id')
            ->with([
                ...$translations,
                'children' => fn (Relation $query) => $query
                    ->orderBy('position')
                    ->orderBy('id')
                    ->with($translations),
            ])
            ->get();
    }

    /**
     * One item with its target translation in its own locale (any status).
     */
    public function forEditor(MenuItem $item): MenuItem
    {
        return $item->load($this->targetTranslations($item->locale));
    }

    /**
     * First-level items of the menu that may receive children, in order.
     *
     * @return Collection<int, MenuItem>
     */
    public function parentOptions(MenuLocation $location, string $locale, ?int $exceptId = null): Collection
    {
        return $this->menuQuery($location, $locale)
            ->whereNull('parent_id')
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->with($this->targetTranslations($locale))
            ->get();
    }

    /**
     * Page translations in the locale, for the target select.
     *
     * @return Collection<int, PageTranslation>
     */
    public function pageTargets(string $locale): Collection
    {
        return PageTranslation::query()
            ->where('locale', $locale)
            ->orderBy('title')
            ->get(['id', 'page_id', 'locale', 'title', 'status']);
    }

    /**
     * Article translations in the locale, for the target select.
     *
     * @return Collection<int, ArticleTranslation>
     */
    public function articleTargets(string $locale): Collection
    {
        return ArticleTranslation::query()
            ->where('locale', $locale)
            ->orderBy('title')
            ->get(['id', 'article_id', 'locale', 'title', 'status', 'published_at']);
    }

    /**
     * @return Builder<MenuItem>
     */
    private function menuQuery(MenuLocation $location, string $locale): Builder
    {
        return MenuItem::query()
            ->where('location', $location->value)
            ->where('locale', $locale)
            ->orderByRaw('parent_id is not null')
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * @return array<string, \Closure(Relation<*, *, *>): mixed>
     */
    private function targetTranslations(string $locale): array
    {
        return [
            'pageTranslations' => fn (Relation $query) => $query
                ->where('locale', $locale)
                ->select(['id', 'page_id', 'locale', 'title', 'status']),
            'articleTranslations' => fn (Relation $query) => $query
                ->where('locale', $locale)
                ->select(['id', 'article_id', 'locale', 'title', 'status', 'published_at']),
        ];
    }
}
