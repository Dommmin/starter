<?php

namespace App\Http\Controllers\Admin\HomeSections;

use App\Actions\Home\ReorderHomeSections;
use App\Actions\Home\ToggleHomeSection;
use App\Actions\Home\UpdateHomeSection;
use App\Data\Admin\HomeSections\HomePageOptionData;
use App\Data\Admin\HomeSections\HomeSectionAbilitiesData;
use App\Data\Admin\HomeSections\HomeSectionEditorData;
use App\Data\Admin\HomeSections\HomeSectionFormData;
use App\Data\Admin\HomeSections\HomeSectionIndexData;
use App\Data\Admin\HomeSections\HomeSectionListItemData;
use App\Data\Content\ContentLocaleData;
use App\Data\Content\ContentLocalesData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeSections\ListHomeSectionsRequest;
use App\Http\Requests\Admin\HomeSections\ReorderHomeSectionsRequest;
use App\Http\Requests\Admin\HomeSections\ToggleHomeSectionRequest;
use App\Http\Requests\Admin\HomeSections\UpdateHomeSectionRequest;
use App\Models\HomeSection;
use App\Models\User;
use App\Repositories\Content\PageRepository;
use App\Repositories\Home\HomeSectionRepository;
use App\Services\Home\HomeLinkResolver;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin editing of the home page sections: list per locale, edit content,
 * show/hide and reorder. Sections are never created or deleted here.
 * Authorization: `can` middleware on every route (HomeSectionPolicy),
 * re-checked by the mutation requests.
 */
class HomeSectionController extends Controller
{
    public function __construct(
        private readonly HomeSectionRepository $sections,
        private readonly PageRepository $pages,
        private readonly HomeLinkResolver $links,
        private readonly LocalizationConfig $localization,
    ) {}

    /**
     * Display every section of the selected content locale in order.
     */
    public function index(ListHomeSectionsRequest $request): Response
    {
        $locale = $request->locale();

        $items = [];
        foreach ($this->sections->forLocale($locale) as $section) {
            $items[] = HomeSectionListItemData::fromModel($section);
        }

        return Inertia::render('admin/home-sections/index', new HomeSectionIndexData(
            items: $items,
            locale: $locale,
            locales: ContentLocalesData::fromConfig($this->localization),
            can: $this->abilities($request, null),
        ));
    }

    /**
     * Show the content form of the section.
     */
    public function edit(Request $request, HomeSection $homeSection): Response
    {
        $pages = [];
        foreach ($this->pages->publishedInLocale($homeSection->locale) as $pageId => $translation) {
            $pages[] = new HomePageOptionData(id: $pageId, title: $translation->title);
        }

        $meta = $this->localization->getLocaleMetadata($homeSection->locale);

        return Inertia::render('admin/home-sections/edit', new HomeSectionEditorData(
            section: HomeSectionFormData::fromModel($homeSection),
            locale: new ContentLocaleData(
                code: $homeSection->locale,
                name: $meta['name'] ?? $homeSection->locale,
                native: $meta['native'] ?? $homeSection->locale,
                dir: $meta['dir'] ?? 'ltr',
            ),
            linkTargets: $this->links->availableTargets(),
            pages: $pages,
            can: $this->abilities($request, $homeSection),
        ));
    }

    /**
     * Replace the content; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdateHomeSectionRequest $request, HomeSection $homeSection, UpdateHomeSection $updateHomeSection): RedirectResponse
    {
        $updateHomeSection->handle($homeSection, $this->actor($request), $request->content(), $request->expectedUpdatedAt());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.homeSections.updated')]);

        return to_route('admin.home-sections.edit', $homeSection);
    }

    /**
     * Show or hide the section on the public page.
     */
    public function visibility(ToggleHomeSectionRequest $request, HomeSection $homeSection, ToggleHomeSection $toggleHomeSection): RedirectResponse
    {
        $toggleHomeSection->handle($homeSection, $this->actor($request), $request->boolean('enabled'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.homeSections.visibilityUpdated')]);

        return to_route('admin.home-sections.index', ['locale' => $homeSection->locale]);
    }

    /**
     * Apply a complete new order of the locale's sections.
     */
    public function reorder(ReorderHomeSectionsRequest $request, ReorderHomeSections $reorderHomeSections): RedirectResponse
    {
        $locale = $request->string('locale')->toString();

        $reorderHomeSections->handle($locale, $request->ids(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.homeSections.reordered')]);

        return to_route('admin.home-sections.index', ['locale' => $locale]);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function abilities(Request $request, ?HomeSection $section): HomeSectionAbilitiesData
    {
        $user = $request->user();

        return new HomeSectionAbilitiesData(
            update: $section !== null
                ? ($user?->can('update', $section) ?? false)
                : ($user?->canAccessAdminPanel() ?? false),
            reorder: $user?->can('reorder', HomeSection::class) ?? false,
        );
    }
}
