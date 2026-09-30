<?php

namespace App\Http\Controllers\Admin\Content;

use App\Data\Content\ContentPreviewData;
use App\Data\Content\PublicArticleData;
use App\Data\Content\PublicPageData;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Page;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Services\Content\RichTextRenderer;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use App\Services\Navigation\PublicNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed, panel-only preview of the saved translation of a page or article
 * in any publication state. Renders the public screen (same component, public
 * chrome and catalog of the content locale) with a preview banner, never
 * indexable or cacheable. Authorization: panel middleware, `signed` and the
 * `preview` policy ability on the route.
 */
class ContentPreviewController extends Controller
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ArticleRepository $articles,
        private readonly RichTextRenderer $richText,
        private readonly LocalizationConfig $config,
        private readonly LocalizationManager $localization,
        private readonly LocalizedUrlGenerator $urls,
        private readonly PublicNavigation $navigation,
    ) {}

    /**
     * Preview the saved page translation of the given locale.
     */
    public function page(Request $request, Page $page, string $contentLocale): Response
    {
        $translation = $this->config->isPublicLocale($contentLocale)
            ? $this->pages->findForPreview($page, $contentLocale)
            : null;

        abort_if($translation === null, 404);

        $this->usePublicContext($request, $contentLocale);

        return $this->privateResponse($request, Inertia::render('pages/show', PublicPageData::fromTranslation(
            $translation,
            $this->richText->toHtml($translation->body),
            [],
            ContentPreviewData::forPage($translation, route('admin.pages.edit', $page)),
        )));
    }

    /**
     * Preview the saved article translation of the given locale.
     */
    public function article(Request $request, Article $article, string $contentLocale): Response
    {
        $translation = $this->config->isPublicLocale($contentLocale)
            ? $this->articles->findForPreview($article, $contentLocale)
            : null;

        abort_if($translation === null, 404);

        $this->usePublicContext($request, $contentLocale);

        return $this->privateResponse($request, Inertia::render('articles/show', PublicArticleData::fromTranslation(
            $translation,
            $this->richText->toHtml($translation->body),
            $this->urls->url('articles.index', [], $contentLocale),
            [],
            ContentPreviewData::forArticle($translation, route('admin.articles.edit', $article)),
        )));
    }

    /**
     * The route lives in the admin area; the previewed screen needs the
     * public catalog, menus and HTML language of the content locale, and no
     * language alternates (the translations may not be public).
     */
    private function usePublicContext(Request $request, string $locale): void
    {
        $this->localization->setContext('public', $locale, $request);
        app()->setLocale($locale);
        app()->setFallbackLocale($this->config->getPublicFallback());

        Inertia::share('locale', $locale);
        Inertia::share('i18n', [...$this->localization->getPayload($request), 'alternateUrls' => []]);
        Inertia::share('navigation', fn () => $this->navigation->shared($locale));
    }

    /**
     * Unpublished content must not be indexed or stored by any cache. The
     * public context switch disables the admin-area X-Robots-Tag of
     * PreventIndexingOfPrivateAreas, so it is set here. Links in the content
     * must not carry the signed URL to other sites in the Referer header.
     */
    private function privateResponse(Request $request, InertiaResponse $page): Response
    {
        $response = $page->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
