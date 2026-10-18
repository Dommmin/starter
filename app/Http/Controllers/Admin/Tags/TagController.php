<?php

namespace App\Http\Controllers\Admin\Tags;

use App\Actions\Audit\ShareRecordHistory;
use App\Actions\Tags\SaveTag;
use App\Data\Admin\Tags\TagEditorData;
use App\Data\Admin\Tags\TagFormData;
use App\Data\Admin\Tags\TagIndexData;
use App\Data\Admin\Tags\TagListItemData;
use App\Data\Content\ContentLocalesData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tags\SaveTagRequest;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\Content\TagRepository;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD of article tags (`can` middleware with TagPolicy on every
 * route, re-checked by the save request).
 */
class TagController extends Controller
{
    public function __construct(
        private readonly LocalizationConfig $localization,
        private readonly TagRepository $tags,
    ) {}

    public function index(Request $request): Response
    {
        $locale = app()->getLocale();
        $fallback = $this->localization->getPublicFallback();

        return Inertia::render('admin/tags/index', new TagIndexData(
            items: array_values($this->tags->allWithArticleCounts()->map(
                fn (Tag $tag): TagListItemData => TagListItemData::fromModel($tag, $locale, $fallback),
            )->all()),
            canCreate: $request->user()?->can('create', Tag::class) ?? false,
            canImport: $request->user()?->can('import', Tag::class) ?? false,
        ));
    }

    public function create(): Response
    {
        return Inertia::render('admin/tags/create', $this->editor(
            TagFormData::blank($this->localization->getPublicLocales()),
            canDelete: false,
        ));
    }

    public function store(SaveTagRequest $request, SaveTag $saveTag): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tag = $saveTag->handle(null, $request->tagValues(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.tags.created')]);

        return to_route('admin.tags.edit', $tag);
    }

    public function edit(Request $request, Tag $tag, ShareRecordHistory $recordHistory): Response
    {
        $recordHistory->handle($request->user(), $tag);

        return Inertia::render('admin/tags/edit', $this->editor(
            TagFormData::fromModel($tag, $this->localization->getPublicLocales()),
            canDelete: $request->user()?->can('delete', $tag) ?? false,
            canUpdate: $request->user()?->can('update', $tag) ?? false,
        ));
    }

    /**
     * Rename the tag; a stale `updated_at` yields a `conflict` error.
     */
    public function update(SaveTagRequest $request, Tag $tag, SaveTag $saveTag): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saveTag->handle($tag, $request->tagValues(), $user, $request->expectedUpdatedAt());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.tags.updated')]);

        return to_route('admin.tags.edit', $tag);
    }

    /**
     * Delete the tag; the articles stay and only lose the tag.
     */
    public function destroy(Request $request, Tag $tag, SaveTag $saveTag): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saveTag->delete($tag, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.tags.deleted')]);

        return to_route('admin.tags.index');
    }

    private function editor(TagFormData $tag, bool $canDelete, bool $canUpdate = true): TagEditorData
    {
        return new TagEditorData(
            tag: $tag,
            locales: ContentLocalesData::fromConfig($this->localization),
            canDelete: $canDelete,
            canUpdate: $canUpdate,
        );
    }
}
