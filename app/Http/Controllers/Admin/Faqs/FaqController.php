<?php

namespace App\Http\Controllers\Admin\Faqs;

use App\Actions\Faqs\CreateFaq;
use App\Actions\Faqs\DuplicateFaq;
use App\Actions\Faqs\ReorderFaqs;
use App\Actions\Faqs\UpdateFaq;
use App\Data\Admin\Faqs\FaqAbilitiesData;
use App\Data\Admin\Faqs\FaqEditorData;
use App\Data\Admin\Faqs\FaqFormData;
use App\Data\Admin\Faqs\FaqIndexData;
use App\Data\Admin\Faqs\FaqListFiltersData;
use App\Data\Admin\Faqs\FaqListItemData;
use App\Data\Admin\Faqs\FaqOrderData;
use App\Data\Admin\Faqs\FaqOrderItemData;
use App\Data\Content\ContentLocalesData;
use App\Data\Listing\ListPaginationData;
use App\Enums\AssistantFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Faqs\ListFaqsRequest;
use App\Http\Requests\Admin\Faqs\ReorderFaqsRequest;
use App\Http\Requests\Admin\Faqs\StoreFaqRequest;
use App\Http\Requests\Admin\Faqs\StoreManyFaqsRequest;
use App\Http\Requests\Admin\Faqs\UpdateFaqRequest;
use App\Models\Faq;
use App\Models\User;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            locales: ContentLocalesData::fromConfig($this->localization),
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
    public function store(StoreFaqRequest $request, CreateFaq $createFaq): RedirectResponse
    {
        /** @var array{question: string, answer: string, locale?: string|null, position?: int|null, published: bool} $attributes */
        $attributes = $request->validated();
        $faq = $createFaq->handle($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.faqs.created')]);

        return to_route('admin.faqs.edit', $faq);
    }

    /**
     * Store the FAQ entries the admin ticked among the assistant's proposals,
     * all or none, unpublished.
     */
    public function storeMany(StoreManyFaqsRequest $request, CreateFaq $createFaq): RedirectResponse
    {
        $faqs = $request->faqs();

        DB::transaction(function () use ($faqs, $createFaq): void {
            foreach ($faqs as $attributes) {
                $createFaq->handle($attributes);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.faqs.createdMany', ['count' => count($faqs)])]);

        return to_route('admin.faqs.index');
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
     * Copy the faq as a new, unpublished faq and open the copy's editor.
     */
    public function duplicate(Request $request, Faq $faq, DuplicateFaq $duplicateFaq): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $copy = $duplicateFaq->handle($faq, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.faqs.duplicated')]);

        return to_route('admin.faqs.edit', $copy);
    }

    /**
     * Show every faq in the shared order of all languages (the order of each
     * public locale list), unpaginated, for moving up and down.
     */
    public function order(Request $request): Response
    {
        $items = [];
        foreach (Faq::query()->orderByRaw('position is null')->orderBy('position')->orderBy('id')->get() as $faq) {
            $items[] = FaqOrderItemData::fromModel($faq);
        }

        return Inertia::render('admin/faqs/order', new FaqOrderData(
            items: $items,
            canReorder: $request->user()?->can('reorder', Faq::class) ?? false,
            locales: ContentLocalesData::fromConfig($this->localization),
        ));
    }

    /**
     * Store a complete new shared order of all faqs; a stale list yields a
     * `conflict` error and nothing is stored.
     */
    public function reorder(ReorderFaqsRequest $request, ReorderFaqs $reorderFaqs): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $reorderFaqs->handle($user, $request->ids());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.faqs.reordered')]);

        return to_route('admin.faqs.order');
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
            update: $faq !== null
                ? ($user?->can('update', $faq) ?? false)
                : ($user?->can('create', Faq::class) ?? false),
            delete: $faq !== null
                ? ($user?->can('delete', $faq) ?? false)
                : ($user?->isAdmin() ?? false),
            import: $user?->can('import', Faq::class) ?? false,
            duplicate: $faq !== null
                ? ($user?->can('duplicate', $faq) ?? false)
                : ($user?->can('create', Faq::class) ?? false),
            suggestFaqs: AssistantFeature::FaqGenerator->isAvailable()
                && ($user?->can('create', Faq::class) ?? false),
        );
    }
}
