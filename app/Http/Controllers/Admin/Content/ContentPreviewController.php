<?php

namespace App\Http\Controllers\Admin\Content;

use App\Data\Content\ContentPreviewData;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Page;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Services\Content\ContentPreviewRenderer;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed, panel-only preview of the saved translation of a page or article
 * in any publication state. Authorization: panel middleware, `signed` and
 * the `preview` policy ability on the route.
 */
class ContentPreviewController extends Controller
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ArticleRepository $articles,
        private readonly LocalizationConfig $config,
        private readonly ContentPreviewRenderer $renderer,
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

        return $this->renderer->page(
            $request,
            $translation,
            ContentPreviewData::forPage($translation, route('admin.pages.edit', $page)),
            $request->user(),
        );
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

        return $this->renderer->article(
            $request,
            $translation,
            ContentPreviewData::forArticle($translation, route('admin.articles.edit', $article)),
        );
    }
}
