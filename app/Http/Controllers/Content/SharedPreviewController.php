<?php

namespace App\Http\Controllers\Content;

use App\Data\Content\ContentPreviewData;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentPreviewLink;
use App\Models\Page;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Services\Content\ContentPreviewRenderer;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public preview behind a secret, expiring and revocable link. Every
 * failure is the same 404, so a guess learns nothing. Visitors get no way
 * into the panel and section links are resolved as for a guest.
 */
class SharedPreviewController extends Controller
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ArticleRepository $articles,
        private readonly LocalizationConfig $config,
        private readonly ContentPreviewRenderer $renderer,
    ) {}

    public function __invoke(Request $request, string $token): Response
    {
        $link = ContentPreviewLink::query()
            ->where('token_hash', ContentPreviewLink::hashToken($token))
            ->where('expires_at', '>', now())
            ->first();

        abort_if($link === null || ! $this->config->isPublicLocale($link->locale), 404);

        $response = $link->subject_type === (new Page)->getMorphClass()
            ? $this->page($request, $link)
            : $this->article($request, $link);

        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function page(Request $request, ContentPreviewLink $link): Response
    {
        $page = Page::query()->find($link->subject_id);
        $translation = $page === null ? null : $this->pages->findForPreview($page, $link->locale);

        abort_if($translation === null, 404);

        return $this->renderer->page($request, $translation, ContentPreviewData::forPage($translation, null), null);
    }

    private function article(Request $request, ContentPreviewLink $link): Response
    {
        $article = Article::query()->find($link->subject_id);
        $translation = $article === null ? null : $this->articles->findForPreview($article, $link->locale);

        abort_if($translation === null, 404);

        return $this->renderer->article($request, $translation, ContentPreviewData::forArticle($translation, null));
    }
}
