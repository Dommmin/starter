<?php

namespace App\Http\Controllers\Admin\Trash;

use App\Actions\Content\ManageTrashedContent;
use App\Data\Admin\Trash\TrashIndexData;
use App\Data\Admin\Trash\TrashItemData;
use App\Enums\TrashedContentType;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use App\Repositories\Content\TrashRepository;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trash of pages and articles (administrators only): restore a record or
 * remove it for good before `content:purge-trash` does after the
 * retention period.
 */
class TrashController extends Controller
{
    public function __construct(
        private readonly TrashRepository $trash,
        private readonly LocalizationConfig $localization,
    ) {}

    public function index(): Response
    {
        $locale = $this->localization->getPublicDefault();
        $days = (int) config('content.trash_days');

        $items = [];
        foreach (TrashedContentType::cases() as $type) {
            foreach ($this->trash->trashed($type) as $record) {
                $items[] = TrashItemData::fromRecord($record, $locale, $days);
            }
        }

        return Inertia::render('admin/trash/index', new TrashIndexData(
            items: $items,
            retentionDays: $days,
        ));
    }

    public function restore(Request $request, TrashedContentType $type, int $id, ManageTrashedContent $manage): RedirectResponse
    {
        $record = $this->find($type, $id);
        Gate::authorize('restore', $record);

        /** @var User $user */
        $user = $request->user();
        $manage->restore($record, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.trash.restored')]);

        return to_route($type === TrashedContentType::Pages ? 'admin.pages.edit' : 'admin.articles.edit', $record);
    }

    public function destroy(Request $request, TrashedContentType $type, int $id, ManageTrashedContent $manage): RedirectResponse
    {
        $record = $this->find($type, $id);
        Gate::authorize('forceDelete', $record);

        /** @var User $user */
        $user = $request->user();
        $manage->purge($record, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.trash.purged')]);

        return to_route('admin.trash.index');
    }

    private function find(TrashedContentType $type, int $id): Page|Article
    {
        $record = $this->trash->findTrashed($type, $id);
        abort_if($record === null, 404);

        return $record;
    }
}
