<?php

namespace App\Http\Controllers\Content;

use App\Data\Content\ArticleSummaryData;
use App\Data\Content\PublicArticleData;
use App\Data\Content\PublicArticleListData;
use App\Data\Listing\ListPaginationData;
use App\Http\Controllers\Controller;
use App\Repositories\Content\ArticleRepository;
use App\Services\Content\RichTextRenderer;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicArticleController extends Controller
{
    private const int PER_PAGE = 12;

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly RichTextRenderer $richText,
        private readonly LocalizationConfig $config,
        private readonly LocalizationManager $localization,
        private readonly LocalizedUrlGenerator $urls,
    ) {}

    /**
     * List the newest visible articles of the current public locale. A page
     * number beyond the last page is a 404.
     */
    public function index(Request $request): Response
    {
        $locale = $this->localization->getCurrentLocale($request);
        $paginator = $this->articles->publishedPage($locale, self::PER_PAGE);

        abort_if($paginator->currentPage() > max(1, $paginator->lastPage()), 404);

        $items = [];
        foreach ($paginator->items() as $translation) {
            $items[] = ArticleSummaryData::fromTranslation(
                $translation,
                $this->urls->url('articles.show', ['slug' => $translation->slug], $locale),
            );
        }

        return Inertia::render('articles/index', new PublicArticleListData(
            items: $items,
            pagination: new ListPaginationData(
                page: $paginator->currentPage(),
                totalPages: $paginator->lastPage(),
                total: $paginator->total(),
                perPage: $paginator->perPage(),
            ),
            locale: $locale,
        ));
    }

    /**
     * Show a visible article translation in the current public locale. A
     * former slug of a visible translation answers with a single 301 to its
     * current URL. Drafts, scheduled and missing translations and unknown
     * slugs are indistinguishable 404s.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $locale = $this->localization->getCurrentLocale($request);
        $slug = (string) $request->route('slug');

        $translation = $this->articles->findPublishedTranslation($locale, $slug);

        if ($translation === null) {
            $target = $this->articles->findPublishedRedirectTarget($locale, $slug);

            abort_if($target === null, 404);

            return redirect()->to(
                $this->urls->url('articles.show', ['slug' => $target->slug], $target->locale),
                301,
            );
        }

        $alternates = [];
        foreach ($this->config->getPublicLocales() as $alternateLocale) {
            $alternate = $translation->article->translation($alternateLocale);

            if ($alternate !== null) {
                $alternates[$alternateLocale] = $this->urls->url('articles.show', ['slug' => $alternate->slug], $alternateLocale);
            }
        }

        $default = $this->config->getPublicDefault();
        if (isset($alternates[$default])) {
            $alternates['x-default'] = $alternates[$default];
        }

        Inertia::share('i18n.alternateUrls', $alternates);

        return Inertia::render('articles/show', PublicArticleData::fromTranslation(
            $translation,
            $this->richText->toHtml($translation->body),
            $this->urls->url('articles.index', [], $locale),
            $alternates,
        ));
    }
}
