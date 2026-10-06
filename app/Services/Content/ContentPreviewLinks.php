<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\Page;
use Illuminate\Support\Facades\URL;

/**
 * Short-lived signed URLs of the admin content preview. One place for the
 * route names and the lifetime shared by the page and article editors.
 */
class ContentPreviewLinks
{
    /**
     * Lifetime of a preview link; the editor issues fresh links on every visit and save.
     */
    public const int TTL_MINUTES = 30;

    /**
     * Preview URL of every saved translation of the page. Requires the
     * `translations` relation to be eager loaded.
     *
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    public function forPage(Page $page, array $locales): array
    {
        $urls = [];

        foreach ($locales as $locale) {
            if ($page->translation($locale) !== null) {
                $urls[$locale] = $this->signed('admin.pages.preview', ['page' => $page, 'contentLocale' => $locale]);
            }
        }

        return $urls;
    }

    /**
     * Preview URL of every saved translation of the article. Requires the
     * `translations` relation to be eager loaded.
     *
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    public function forArticle(Article $article, array $locales): array
    {
        $urls = [];

        foreach ($locales as $locale) {
            if ($article->translation($locale) !== null) {
                $urls[$locale] = $this->signed('admin.articles.preview', ['article' => $article, 'contentLocale' => $locale]);
            }
        }

        return $urls;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function signed(string $routeName, array $parameters): string
    {
        return URL::temporarySignedRoute($routeName, now()->addMinutes(self::TTL_MINUTES), $parameters);
    }
}
