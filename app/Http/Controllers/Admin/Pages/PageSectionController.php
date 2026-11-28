<?php

namespace App\Http\Controllers\Admin\Pages;

use App\Actions\Home\EnsureHomeSections;
use App\Data\Admin\HomeSections\HomeContactFormOptionData;
use App\Data\Admin\HomeSections\HomePageOptionData;
use App\Data\Admin\HomeSections\HomeSectionAbilitiesData;
use App\Data\Admin\HomeSections\HomeSectionEditorData;
use App\Data\Admin\HomeSections\HomeSectionFormData;
use App\Data\Admin\HomeSections\HomeSectionListItemData;
use App\Data\Admin\Pages\PageSectionEditorData;
use App\Data\Admin\Pages\PageSectionsIndexData;
use App\Data\Content\ContentLocaleData;
use App\Data\Content\ContentLocalesData;
use App\Enums\HomeSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeSections\HomeSectionContentRules;
use App\Models\ContactForm;
use App\Models\Page;
use App\Models\PageSection;
use App\Repositories\Contact\ContactFormRepository;
use App\Repositories\Content\PageRepository;
use App\Services\Home\HomeLinkResolver;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for reusable sections attached to a content page.
 */
class PageSectionController extends Controller
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly HomeLinkResolver $links,
        private readonly LocalizationConfig $localization,
    ) {}

    public function index(Request $request, Page $page): Response
    {
        $locale = $this->resolveLocale($request);
        $page->loadMissing('translations');

        $items = array_values(
            $page->sections()
                ->where('locale', $locale)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(static fn (PageSection $section): HomeSectionListItemData => HomeSectionListItemData::fromModel($section))
                ->all(),
        );

        return Inertia::render('admin/pages/sections/index', new PageSectionsIndexData(
            pageId: $page->id,
            pageTitle: $page->translation($locale)->title ?? $page->translation($this->localization->getPublicDefault())->title ?? null,
            locale: $locale,
            locales: ContentLocalesData::fromConfig($this->localization),
            items: $items,
            availableTypes: array_map(static fn (HomeSectionType $type): string => $type->value, HomeSectionType::cases()),
            canUpdate: $request->user()?->can('update', $page) ?? false,
        ));
    }

    public function store(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($this->localization->getPublicLocales())],
            'type' => ['required', 'string', Rule::enum(HomeSectionType::class)],
        ]);

        /** @var string $locale */
        $locale = $validated['locale'];
        $type = HomeSectionType::from((string) $validated['type']);

        $nextPosition = ((int) $page->sections()->where('locale', $locale)->max('position')) + 1;

        $section = $page->sections()->create([
            'locale' => $locale,
            'type' => $type,
            'position' => $nextPosition,
            'enabled' => true,
            'content' => EnsureHomeSections::placeholder($type, $locale),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.pages.sections.created')]);

        return to_route('admin.pages.sections.edit', [$page, $section]);
    }

    public function edit(Request $request, Page $page, PageSection $pageSection): Response
    {
        abort_if($pageSection->page_id !== $page->id, 404);

        $page->loadMissing('translations');

        $pages = [];
        foreach ($this->pages->publishedInLocale($pageSection->locale) as $pageId => $translation) {
            $pages[] = new HomePageOptionData(id: $pageId, title: $translation->title);
        }

        $meta = $this->localization->getLocaleMetadata($pageSection->locale);
        $canUpdate = $request->user()?->can('update', $page) ?? false;

        $editor = new HomeSectionEditorData(
            section: HomeSectionFormData::fromModel($pageSection),
            locale: new ContentLocaleData(
                code: $pageSection->locale,
                name: $meta['name'] ?? $pageSection->locale,
                native: $meta['native'] ?? $pageSection->locale,
                dir: $meta['dir'] ?? 'ltr',
            ),
            linkTargets: $this->links->availableTargets(),
            pages: $pages,
            can: new HomeSectionAbilitiesData(update: $canUpdate, reorder: $canUpdate),
            contactForms: array_values(app(ContactFormRepository::class)->all()->map(
                fn (ContactForm $form): HomeContactFormOptionData => new HomeContactFormOptionData(
                    key: $form->key,
                    title: $form->titleIn($pageSection->locale, $this->localization->getPublicFallback()),
                ),
            )->all()),
        );

        return Inertia::render('admin/pages/sections/edit', new PageSectionEditorData(
            pageId: $page->id,
            pageTitle: $page->translation($pageSection->locale)->title ?? $page->translation($this->localization->getPublicDefault())->title ?? null,
            editor: $editor,
        ));
    }

    public function update(Request $request, Page $page, PageSection $pageSection): RedirectResponse
    {
        abort_if($pageSection->page_id !== $page->id, 404);

        $validated = $request->validate([
            'updated_at' => ['required', 'string', 'date'],
            ...app(HomeSectionContentRules::class)->rules($pageSection->type, $pageSection->locale),
        ]);

        /** @var array<string, mixed> $contentInput */
        $contentInput = $validated['content'];
        $content = $pageSection->type->contentClass()::from($contentInput);
        $expectedUpdatedAt = (string) $validated['updated_at'];

        DB::transaction(function () use ($pageSection, $content, $expectedUpdatedAt): void {
            $locked = PageSection::query()->whereKey($pageSection->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.homeSections.conflict'),
                ]);
            }

            $locked->forceFill([
                'content' => $content,
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.homeSections.updated')]);

        return to_route('admin.pages.sections.edit', [$page, $pageSection]);
    }

    public function visibility(Request $request, Page $page, PageSection $pageSection): RedirectResponse
    {
        abort_if($pageSection->page_id !== $page->id, 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $pageSection->update(['enabled' => (bool) $validated['enabled']]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $pageSection->enabled ? __('admin.pages.sections.shown') : __('admin.pages.sections.hidden'),
        ]);

        return back();
    }

    public function reorder(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($this->localization->getPublicLocales())],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer'],
        ]);

        /** @var list<int> $order */
        $order = array_values(array_map('intval', $validated['order']));
        $locale = (string) $validated['locale'];

        DB::transaction(function () use ($page, $locale, $order): void {
            foreach ($order as $position => $sectionId) {
                $page->sections()
                    ->where('id', $sectionId)
                    ->where('locale', $locale)
                    ->update(['position' => $position + 1]);
            }
        });

        return back();
    }

    public function destroy(Page $page, PageSection $pageSection): RedirectResponse
    {
        abort_if($pageSection->page_id !== $page->id, 404);

        $pageSection->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.pages.sections.deleted')]);

        return back();
    }

    private function resolveLocale(Request $request): string
    {
        $requested = $request->query('locale');
        $available = $this->localization->getPublicLocales();

        if (is_string($requested) && in_array($requested, $available, true)) {
            return $requested;
        }

        return $this->localization->getPublicDefault();
    }
}
