<?php

namespace App\Http\Controllers\Admin\SavedViews;

use App\Data\Admin\SavedViews\SavedListViewData;
use App\Data\Admin\SavedViews\SavedListViewsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavedViews\SaveListViewRequest;
use App\Models\SavedListView;
use App\Models\User;
use App\Support\Listing\SavedListViews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Saved filter views of the admin lists (JSON), private to each user.
 */
class SavedListViewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $list = $request->string('list')->toString();
        abort_unless(SavedListViews::isList($list), 404);

        return $this->views($this->user($request), $list);
    }

    /**
     * Save the view; an existing name of the same list is overwritten.
     */
    public function store(SaveListViewRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $list = (string) $request->validated('list');
        $name = trim((string) $request->validated('name'));

        $existing = SavedListView::query()->whereBelongsTo($user)->where('list', $list)->where('name', $name)->first();
        $count = SavedListView::query()->whereBelongsTo($user)->where('list', $list)->count();

        if ($existing === null && $count >= SavedListViews::MAX_PER_LIST) {
            throw ValidationException::withMessages(['name' => __('admin.savedViews.errors.max', ['max' => SavedListViews::MAX_PER_LIST])]);
        }

        $view = $existing ?? new SavedListView(['list' => $list, 'name' => $name]);
        $view->query = $request->savedQuery();
        $view->user()->associate($user);
        $view->save();

        return $this->views($user, $list);
    }

    public function destroy(Request $request, SavedListView $savedListView): JsonResponse
    {
        Gate::authorize('delete', $savedListView);
        $savedListView->delete();

        return $this->views($this->user($request), $savedListView->list);
    }

    private function views(User $user, string $list): JsonResponse
    {
        $views = SavedListView::query()->whereBelongsTo($user)->where('list', $list)->orderBy('name')->get();

        return response()->json(new SavedListViewsData(
            views: array_values($views->map(SavedListViewData::fromModel(...))->all()),
            max: SavedListViews::MAX_PER_LIST,
        ));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
