<?php

namespace App\Http\Controllers\Admin\Pages;

use App\Actions\Content\CreatePage;
use App\Actions\Content\DeletePage;
use App\Actions\Content\UpdatePage;
use App\Data\Admin\Pages\PageAbilitiesData;
use App\Data\Admin\Pages\PageEditorData;
use App\Data\Admin\Pages\PageFormData;
use App\Data\Admin\Pages\PageIndexData;
use App\Data\Admin\Pages\PageListFiltersData;
use App\Data\Admin\Pages\PageListItemData;
use App\Data\Content\ContentLocalesData;
use App\Data\Listing\ListPaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pages\ListPagesRequest;
use App\Http\Requests\Admin\Pages\StorePageRequest;
use App\Http\Requests\Admin\Pages\UpdatePageRequest;
use App\Models\Page;
use App\Models\User;
use App\Repositories\Content\PageRepository;
use App\Services\Content\ContentPreviewLinks;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD of content pages. Authorization: `can` middleware on every
 * route (PagePolicy) plus the publish check in the store/update requests.
 */
class PageController extends Controller
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ContentPreviewLinks $previewLinks,
        private readonly LocalizationConfig $localization,
    ) {}

    /**
     * Display the searchable, filterable, paginated page list.
     */
    public function index(ListPagesRequest $request): Response
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();
        $filters = $listQuery->filtersPayload($validated);
        $defaultLocale = $this->localization->getPublicDefault();

        $paginator = $listQuery->paginate($this->pages->adminListQuery(), $validated);

        $items = [];
        foreach ($paginator->items() as $page) {
            $items[] = PageListItemData::fromPage($page, $filters['locale'], $defaultLocale);
        }

        return Inertia::render('admin/pages/index', new PageIndexData(
            items: $items,
            pagination: ListPaginationData::from($listQuery->paginationPayload($paginator)),
            filters: PageListFiltersData::from($filters),
            locales: ContentLocalesData::fromConfig($this->localization),
            can: $this->abilities($request, null),
        ));
    }

    /**
     * Show the form for a new page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/pages/create', new PageEditorData(
            page: PageFormData::blank($this->localization->getPublicLocales()),
            locales: ContentLocalesData::fromConfig($this->localization),
            can: $this->abilities($request, null),
        ));
    }

    /**
     * Store a new page with its translations.
     */
    public function store(StorePageRequest $request, CreatePage $createPage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $page = $createPage->handle($user, $request->translations());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.pages.created')]);

        return to_route('admin.pages.edit', $page);
    }

    /**
     * Show the form for editing the page.
     */
    public function edit(Request $request, Page $page): Response
    {
        $page = $this->pages->forEditor($page);

        return Inertia::render('admin/pages/edit', new PageEditorData(
            page: PageFormData::fromPage($page, $this->localization->getPublicLocales()),
            locales: ContentLocalesData::fromConfig($this->localization),
            can: $this->abilities($request, $page),
            previewUrls: $this->previewLinks->forPage($page, $this->localization->getPublicLocales()),
        ));
    }

    /**
     * Update the page translations; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdatePageRequest $request, Page $page, UpdatePage $updatePage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updatePage->handle(
            $page,
            $user,
            $request->translations(),
            $this->localization->getPublicLocales(),
            $request->expectedUpdatedAt(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.pages.updated')]);

        return to_route('admin.pages.edit', $page);
    }

    /**
     * Permanently delete the page with all translations (administrators only).
     */
    public function destroy(Request $request, Page $page, DeletePage $deletePage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deletePage->handle($page, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.pages.deleted')]);

        return to_route('admin.pages.index');
    }

    private function abilities(Request $request, ?Page $page): PageAbilitiesData
    {
        $user = $request->user();

        return new PageAbilitiesData(
            create: $user?->can('create', Page::class) ?? false,
            publish: $user?->can('publish', Page::class) ?? false,
            delete: $page !== null
                ? ($user?->can('delete', $page) ?? false)
                : ($user?->isAdmin() ?? false),
        );
    }
}
