<?php

namespace App\Http\Controllers\Admin\Navigation;

use App\Actions\Navigation\CreateMenuItem;
use App\Actions\Navigation\DeleteMenuItem;
use App\Actions\Navigation\ReorderMenuItems;
use App\Actions\Navigation\UpdateMenuItem;
use App\Data\Admin\Navigation\MenuAbilitiesData;
use App\Data\Admin\Navigation\MenuAnchorOptionData;
use App\Data\Admin\Navigation\MenuIndexData;
use App\Data\Admin\Navigation\MenuItemEditorData;
use App\Data\Admin\Navigation\MenuItemFormData;
use App\Data\Admin\Navigation\MenuParentOptionData;
use App\Data\Admin\Navigation\MenuTargetOptionData;
use App\Data\Admin\Navigation\MenuTreeItemData;
use App\Data\Content\ContentLocalesData;
use App\Enums\HomeSectionType;
use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Navigation\ListMenuItemsRequest;
use App\Http\Requests\Admin\Navigation\ReorderMenuItemsRequest;
use App\Http\Requests\Admin\Navigation\StoreMenuItemRequest;
use App\Http\Requests\Admin\Navigation\UpdateMenuItemRequest;
use App\Models\HomeSection;
use App\Models\MenuItem;
use App\Models\User;
use App\Repositories\Navigation\MenuItemRepository;
use App\Services\Localization\LocalizationConfig;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin management of the public header and footer menus. Authorization:
 * `can` middleware on every route (MenuItemPolicy), re-checked by the
 * mutating requests.
 */
class MenuItemController extends Controller
{
    public function __construct(
        private readonly MenuItemRepository $items,
        private readonly LocalizationConfig $localization,
    ) {}

    /**
     * Display the whole tree of the selected menu.
     */
    public function index(ListMenuItemsRequest $request): Response
    {
        $location = $request->location();
        $locale = $request->locale();

        $items = [];
        foreach ($this->items->adminTree($location, $locale) as $item) {
            $items[] = MenuTreeItemData::fromModel($item);
        }

        return Inertia::render('admin/navigation/index', new MenuIndexData(
            location: $location,
            locale: $locale,
            locales: ContentLocalesData::fromConfig($this->localization),
            items: $items,
            can: $this->abilities($request),
        ));
    }

    /**
     * Show the form for a new item of the selected menu.
     */
    public function create(ListMenuItemsRequest $request): Response
    {
        return Inertia::render('admin/navigation/create', $this->editor(
            $request,
            MenuItemFormData::blank($request->location(), $request->locale()),
        ));
    }

    /**
     * Store a new item at the end of its level.
     */
    public function store(StoreMenuItemRequest $request, CreateMenuItem $createMenuItem): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $createMenuItem->handle($user, $request->location(), $request->menuLocale(), $request->itemInput());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.navigation.created')]);

        return to_route('admin.navigation.index', [
            'location' => $request->location()->value,
            'locale' => $request->menuLocale(),
        ]);
    }

    /**
     * Show the form for editing the item.
     */
    public function edit(Request $request, MenuItem $menuItem): Response
    {
        $menuItem = $this->items->forEditor($menuItem);

        return Inertia::render('admin/navigation/edit', $this->editor(
            $request,
            MenuItemFormData::fromModel($menuItem, $menuItem->children()->exists()),
            $menuItem->id,
        ));
    }

    /**
     * Update the item; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem, UpdateMenuItem $updateMenuItem): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateMenuItem->handle($menuItem, $user, $request->itemInput(), $request->expectedUpdatedAt());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.navigation.updated')]);

        return to_route('admin.navigation.edit', $menuItem);
    }

    /**
     * Permanently delete the item with its children.
     */
    public function destroy(Request $request, MenuItem $menuItem, DeleteMenuItem $deleteMenuItem): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleteMenuItem->handle($menuItem, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.navigation.deleted')]);

        return to_route('admin.navigation.index', [
            'location' => $menuItem->location->value,
            'locale' => $menuItem->locale,
        ]);
    }

    /**
     * Store a new order of one level; a stale set of ids yields `conflict`.
     */
    public function reorder(ReorderMenuItemsRequest $request, ReorderMenuItems $reorderMenuItems): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $reorderMenuItems->handle(
            $user,
            $request->location(),
            $request->menuLocale(),
            $request->parentId(),
            $request->ids(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.navigation.reordered')]);

        return to_route('admin.navigation.index', [
            'location' => $request->location()->value,
            'locale' => $request->menuLocale(),
        ]);
    }

    private function editor(Request $request, MenuItemFormData $item, ?int $exceptParentId = null): MenuItemEditorData
    {
        $now = CarbonImmutable::now();

        $pages = [];
        foreach ($this->items->pageTargets($item->locale) as $translation) {
            $pages[] = new MenuTargetOptionData(
                id: $translation->page_id,
                title: $translation->title,
                draft: $translation->status !== PublicationStatus::Published,
            );
        }

        $articles = [];
        foreach ($this->items->articleTargets($item->locale) as $translation) {
            $articles[] = new MenuTargetOptionData(
                id: $translation->article_id,
                title: $translation->title,
                draft: $translation->status !== PublicationStatus::Published
                    || $translation->published_at === null
                    || $translation->published_at->greaterThan($now),
            );
        }

        $parents = [];
        foreach ($this->items->parentOptions($item->location, $item->locale, $exceptParentId) as $parent) {
            $parents[] = MenuParentOptionData::fromModel($parent);
        }

        $enabledSections = HomeSection::query()
            ->where('locale', $item->locale)
            ->where('enabled', true)
            ->pluck('type')
            ->map(fn (HomeSectionType $type): string => $type->value)
            ->all();

        $homeAnchors = [];
        foreach (HomeSectionType::cases() as $sectionType) {
            $homeAnchors[] = new MenuAnchorOptionData(
                anchor: $sectionType->anchor()->value,
                sectionType: $sectionType,
                enabled: in_array($sectionType->value, $enabledSections, true),
            );
        }

        return new MenuItemEditorData(
            item: $item,
            locales: ContentLocalesData::fromConfig($this->localization),
            pages: $pages,
            articles: $articles,
            parents: $parents,
            homeAnchors: $homeAnchors,
            can: $this->abilities($request),
        );
    }

    private function abilities(Request $request): MenuAbilitiesData
    {
        $user = $request->user();

        return new MenuAbilitiesData(
            create: $user?->can('create', MenuItem::class) ?? false,
            reorder: $user?->can('reorder', MenuItem::class) ?? false,
            delete: $user?->can('delete', new MenuItem) ?? false,
        );
    }
}
