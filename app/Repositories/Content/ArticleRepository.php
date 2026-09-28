<?php

namespace App\Repositories\Content;

use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\ArticleTranslation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Named read queries of the Content module for articles.
 */
class ArticleRepository
{
    /**
     * Base query of the admin article list. The list definition in
     * ListArticlesRequest joins the translation of the selected content locale.
     *
     * @return Builder<Article>
     */
    public function adminListQuery(): Builder
    {
        return Article::query()
            ->select('articles.*')
            ->with('translations');
    }

    /**
     * Load an article with every translation and its cover for the editor.
     */
    public function forEditor(Article $article): Article
    {
        return $article->load(['translations', 'cover']);
    }

    /**
     * Newest visible translations of the given locale for the public list.
     *
     * @return LengthAwarePaginator<int, ArticleTranslation>
     */
    public function publishedPage(string $locale, int $perPage): LengthAwarePaginator
    {
        return ArticleTranslation::query()
            ->published()
            ->where('locale', $locale)
            ->with('article.cover')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Find the translation visitors may see at the given locale and slug,
     * with its cover and sibling translations for language alternates.
     */
    public function findPublishedTranslation(string $locale, string $slug): ?ArticleTranslation
    {
        return ArticleTranslation::query()
            ->published()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with(['article.cover', 'article.translations' => fn ($query) => $query->published()])
            ->first();
    }

    /**
     * Find the visible translation a former slug redirects to in the given
     * locale. A redirect to a draft or scheduled translation resolves to null.
     */
    public function findPublishedRedirectTarget(string $locale, string $oldSlug): ?ArticleTranslation
    {
        return ArticleTranslation::query()
            ->published()
            ->where('locale', $locale)
            ->whereIn('id', ArticleSlugRedirect::query()
                ->select('article_translation_id')
                ->where('locale', $locale)
                ->where('old_slug', $oldSlug))
            ->first();
    }

    /**
     * Articles with at least one visible translation, each with only its
     * visible translations loaded. Drafts and scheduled translations never
     * reach the sitemap.
     *
     * @return Collection<int, Article>
     */
    public function publishedForSitemap(): Collection
    {
        return Article::query()
            ->whereHas('translations', function (Builder $query): void {
                /** @var Builder<ArticleTranslation> $query */
                $query->published();
            })
            ->with(['translations' => fn ($query) => $query->published()->orderBy('locale')])
            ->orderBy('id')
            ->get();
    }
}
