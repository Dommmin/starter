<?php

namespace App\Repositories\Content;

use App\Models\ArticleTranslation;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Named read queries of the Content module for article tags.
 */
class TagRepository
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
}
