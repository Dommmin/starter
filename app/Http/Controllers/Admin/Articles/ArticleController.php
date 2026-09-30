<?php

namespace App\Http\Controllers\Admin\Articles;

use App\Actions\Content\CreateArticle;
use App\Actions\Content\DeleteArticle;
use App\Actions\Content\UpdateArticle;
use App\Data\Admin\Articles\ArticleAbilitiesData;
use App\Data\Admin\Articles\ArticleEditorData;
use App\Data\Admin\Articles\ArticleFormData;
use App\Data\Admin\Articles\ArticleIndexData;
use App\Data\Admin\Articles\ArticleListFiltersData;
use App\Data\Admin\Articles\ArticleListItemData;
use App\Data\Content\ContentLocalesData;
use App\Data\Listing\ListPaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Articles\ListArticlesRequest;
use App\Http\Requests\Admin\Articles\StoreArticleRequest;
use App\Http\Requests\Admin\Articles\UpdateArticleRequest;
use App\Models\Article;
use App\Models\User;
use App\Repositories\Content\ArticleRepository;
use App\Services\Content\ContentPreviewLinks;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD of articles. Authorization: `can` middleware on every route
 * (ArticlePolicy) plus the publish check in the store/update requests.
 */
class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ContentPreviewLinks $previewLinks,
        private readonly LocalizationConfig $localization,
    ) {}

    /**
     * Display the searchable, filterable, paginated article list.
     */
    public function index(ListArticlesRequest $request): Response
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();
        $filters = $listQuery->filtersPayload($validated);
        $defaultLocale = $this->localization->getPublicDefault();

        $paginator = $listQuery->paginate($this->articles->adminListQuery(), $validated);

        $items = [];
        foreach ($paginator->items() as $article) {
            $items[] = ArticleListItemData::fromArticle($article, $filters['locale'], $defaultLocale);
        }

        return Inertia::render('admin/articles/index', new ArticleIndexData(
            items: $items,
            pagination: ListPaginationData::from($listQuery->paginationPayload($paginator)),
            filters: ArticleListFiltersData::from($filters),
            locales: ContentLocalesData::fromConfig($this->localization),
            can: $this->abilities($request, null),
        ));
    }

    /**
     * Show the form for a new article.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/articles/create', new ArticleEditorData(
            article: ArticleFormData::blank($this->localization->getPublicLocales()),
            locales: ContentLocalesData::fromConfig($this->localization),
            can: $this->abilities($request, null),
        ));
    }

    /**
     * Store a new article with its cover and translations.
     */
    public function store(StoreArticleRequest $request, CreateArticle $createArticle): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $article = $createArticle->handle($user, $request->coverMediaId(), $request->translations());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.articles.created')]);

        return to_route('admin.articles.edit', $article);
    }

    /**
     * Show the form for editing the article.
     */
    public function edit(Request $request, Article $article): Response
    {
        $article = $this->articles->forEditor($article);

        return Inertia::render('admin/articles/edit', new ArticleEditorData(
            article: ArticleFormData::fromArticle($article, $this->localization->getPublicLocales()),
            locales: ContentLocalesData::fromConfig($this->localization),
            can: $this->abilities($request, $article),
            previewUrls: $this->previewLinks->forArticle($article, $this->localization->getPublicLocales()),
        ));
    }

    /**
     * Update the article; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdateArticleRequest $request, Article $article, UpdateArticle $updateArticle): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateArticle->handle(
            $article,
            $user,
            $request->coverMediaId(),
            $request->translations(),
            $this->localization->getPublicLocales(),
            $request->expectedUpdatedAt(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.articles.updated')]);

        return to_route('admin.articles.edit', $article);
    }

    /**
     * Permanently delete the article with all translations (administrators only).
     */
    public function destroy(Request $request, Article $article, DeleteArticle $deleteArticle): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleteArticle->handle($article, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.articles.deleted')]);

        return to_route('admin.articles.index');
    }

    private function abilities(Request $request, ?Article $article): ArticleAbilitiesData
    {
        $user = $request->user();

        return new ArticleAbilitiesData(
            create: $user?->can('create', Article::class) ?? false,
            publish: $user?->can('publish', Article::class) ?? false,
            delete: $article !== null
                ? ($user?->can('delete', $article) ?? false)
                : ($user?->isAdmin() ?? false),
        );
    }
}
