<?php

namespace App\Repositories\Content;

use App\Contracts\Search\AdminSearchSource;
use App\Data\Admin\Search\AdminSearchResultData;
use App\Models\ArticleTranslation;
use App\Models\Tag;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Named read queries of the Content module for article tags.
 */
class TagRepository implements AdminSearchSource
{
    /**
     * Every tag with the number of its articles, by slug (admin list).
     *
     * @return Collection<int, Tag>
     */
    public function allWithArticleCounts(): Collection
    {
        return Tag::query()->withCount('articles')->orderBy('slug')->get();
    }

    /**
     * Every tag by slug (options of the article editor).
     *
     * @return Collection<int, Tag>
     */
    public function all(): Collection
    {
        return Tag::query()->orderBy('slug')->get();
    }

    /**
     * Tags with at least one article visible now in the locale (filter of
     * the public article list), by slug.
     *
     * @return Collection<int, Tag>
     */
    public function withPublishedArticles(string $locale): Collection
    {
        return Tag::query()
            ->whereHas('articles.translations', function (Builder $query) use ($locale): void {
                /** @var Builder<ArticleTranslation> $query */
                $query->published()->where('locale', $locale);
            })
            ->orderBy('slug')
            ->get();
    }

    public function findBySlug(string $slug): ?Tag
    {
        return Tag::query()->where('slug', $slug)->first();
    }

    public function model(): string
    {
        return Tag::class;
    }

    /**
     * Tags whose slug or name in any language contains the term. Tags are
     * few, so names (JSON) are matched in PHP, independent of the database.
     */
    public function search(string $term, int $limit): array
    {
        $needle = mb_strtolower($term);
        $locale = app()->getLocale();
        $fallback = app(LocalizationConfig::class)->getPublicFallback();

        $results = [];
        foreach ($this->all() as $tag) {
            $haystack = mb_strtolower($tag->slug.' '.implode(' ', $tag->name));

            if (! str_contains($haystack, $needle)) {
                continue;
            }

            $results[] = new AdminSearchResultData(
                type: 'tags',
                id: $tag->id,
                label: $tag->nameIn($locale, $fallback),
                description: $tag->slug,
                url: route('admin.tags.edit', $tag),
            );

            if (count($results) === $limit) {
                break;
            }
        }

        return $results;
    }
}
