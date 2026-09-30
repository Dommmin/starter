<?php

namespace App\Http\Controllers\Admin\Faqs;

use App\Actions\Faqs\UpdateFaq;
use App\Data\Admin\Faqs\FaqAbilitiesData;
use App\Data\Admin\Faqs\FaqEditorData;
use App\Data\Admin\Faqs\FaqFormData;
use App\Data\Admin\Faqs\FaqIndexData;
use App\Data\Admin\Faqs\FaqListFiltersData;
use App\Data\Admin\Faqs\FaqListItemData;
use App\Data\Content\ContentLocalesData;
use App\Data\Listing\ListPaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Faqs\ListFaqsRequest;
use App\Http\Requests\Admin\Faqs\StoreFaqRequest;
use App\Http\Requests\Admin\Faqs\UpdateFaqRequest;
use App\Models\Faq;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD of faqs. Authorization: `can` middleware on every
 * route (FaqPolicy), re-checked by the store/update requests.
 */
class FaqController extends Controller
{
    public function __construct(
        private readonly LocalizationConfig $localization,
    ) {}

    /**
     * Display the searchable, filterable, paginated list.
     */
    public function index(ListFaqsRequest $request): Response
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();

        $paginator = $listQuery->paginate(Faq::query(), $validated);

        $items = [];
        foreach ($paginator->items() as $faq) {
            $items[] = FaqListItemData::fromModel($faq);
        }

        return Inertia::render('admin/faqs/index', new FaqIndexData(
            items: $items,
            pagination: ListPaginationData::from($listQuery->paginationPayload($paginator)),
            filters: FaqListFiltersData::from($listQuery->filtersPayload($validated)),
            can: $this->abilities($request, null),
        ));
    }

    /**
     * Show the form for a new faq.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/faqs/create', new FaqEditorData(
            faq: FaqFormData::blank(),
            can: $this->abilities($request, null),
            locales: ContentLocalesData::fromConfig($this->localization),
        ));
    }

    /**
     * Store a new faq.
     */
    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $faq = Faq::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.faqs.created')]);

        return to_route('admin.faqs.edit', $faq);
    }

    /**
     * Show the form for editing the faq.
     */
    public function edit(Request $request, Faq $faq): Response
    {
        return Inertia::render('admin/faqs/edit', new FaqEditorData(
            faq: FaqFormData::fromModel($faq),
            can: $this->abilities($request, $faq),
            locales: ContentLocalesData::fromConfig($this->localization),
        ));
    }

    /**
     * Update the faq; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdateFaqRequest $request, Faq $faq, UpdateFaq $updateFaq): RedirectResponse
    {
        $updateFaq->handle($faq, $request->fieldValues(), $request->expectedUpdatedAt());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.faqs.updated')]);

        return to_route('admin.faqs.edit', $faq);
    }

    /**
     * Permanently delete the faq (administrators only).
     */
    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.faqs.deleted')]);

        return to_route('admin.faqs.index');
    }

    private function abilities(Request $request, ?Faq $faq): FaqAbilitiesData
    {
        $user = $request->user();

        return new FaqAbilitiesData(
            create: $user?->can('create', Faq::class) ?? false,
            delete: $faq !== null
                ? ($user?->can('delete', $faq) ?? false)
                : ($user?->isAdmin() ?? false),
        );
    }
}
