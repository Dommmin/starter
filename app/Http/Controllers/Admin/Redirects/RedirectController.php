<?php

namespace App\Http\Controllers\Admin\Redirects;

use App\Actions\Audit\ShareRecordHistory;
use App\Actions\Redirects\SaveRedirect;
use App\Data\Admin\Redirects\RedirectAbilitiesData;
use App\Data\Admin\Redirects\RedirectEditorData;
use App\Data\Admin\Redirects\RedirectFormData;
use App\Data\Admin\Redirects\RedirectIndexData;
use App\Data\Admin\Redirects\RedirectListFiltersData;
use App\Data\Admin\Redirects\RedirectListItemData;
use App\Data\Listing\ListPaginationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Redirects\DestroyRedirectsRequest;
use App\Http\Requests\Admin\Redirects\ListRedirectsRequest;
use App\Http\Requests\Admin\Redirects\StoreRedirectRequest;
use App\Http\Requests\Admin\Redirects\UpdateRedirectRequest;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD of managed redirects (administrators only). Authorization:
 * `can` middleware on every route (RedirectPolicy), re-checked by the
 * store/update requests; every mutation is audited by SaveRedirect.
 */
class RedirectController extends Controller
{
    /**
     * Display the searchable, filterable, paginated list.
     */
    public function index(ListRedirectsRequest $request): Response
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();

        $paginator = $listQuery->paginate(Redirect::query(), $validated);

        $items = [];
        foreach ($paginator->items() as $redirect) {
            $items[] = RedirectListItemData::fromModel($redirect);
        }

        return Inertia::render('admin/redirects/index', new RedirectIndexData(
            items: $items,
            pagination: ListPaginationData::from($listQuery->paginationPayload($paginator)),
            filters: RedirectListFiltersData::from($listQuery->filtersPayload($validated)),
            can: $this->abilities($request, null),
        ));
    }

    /**
     * Show the form for a new redirect.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/redirects/create', $this->editor($request, RedirectFormData::blank(), null));
    }

    /**
     * Store a new redirect.
     */
    public function store(StoreRedirectRequest $request, SaveRedirect $saveRedirect): RedirectResponse
    {
        $redirect = $saveRedirect->handle(null, $request->fieldValues(), $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.redirects.created')]);

        return to_route('admin.redirects.edit', $redirect);
    }

    /**
     * Show the form for editing the redirect.
     */
    public function edit(Request $request, Redirect $redirect, ShareRecordHistory $recordHistory): Response
    {
        $recordHistory->handle($request->user(), $redirect);

        return Inertia::render('admin/redirects/edit', $this->editor($request, RedirectFormData::fromModel($redirect), $redirect));
    }

    /**
     * Update the redirect; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdateRedirectRequest $request, Redirect $redirect, SaveRedirect $saveRedirect): RedirectResponse
    {
        $saveRedirect->handle($redirect, $request->fieldValues(), $this->user($request), $request->expectedUpdatedAt());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.redirects.updated')]);

        return to_route('admin.redirects.edit', $redirect);
    }

    /**
     * Permanently delete the redirect (administrators only).
     */
    public function destroy(Request $request, Redirect $redirect, SaveRedirect $saveRedirect): RedirectResponse
    {
        $saveRedirect->delete([$redirect->id], $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.redirects.deleted')]);

        return to_route('admin.redirects.index');
    }

    /**
     * Permanently delete the selected redirects of one list page: every
     * record is authorized for `delete` before any is removed (all or nothing);
     * ids that no longer exist are skipped, so a repeated request is harmless.
     */
    public function destroyMany(DestroyRedirectsRequest $request, SaveRedirect $saveRedirect): RedirectResponse
    {
        $user = $this->user($request);
        $deleted = $saveRedirect->delete(
            $request->ids(),
            $user,
            fn (Redirect $record) => Gate::forUser($user)->authorize('delete', $record),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.redirects.deletedMany', ['count' => $deleted])]);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function editor(Request $request, RedirectFormData $form, ?Redirect $redirect): RedirectEditorData
    {
        return new RedirectEditorData(
            redirect: $form,
            can: $this->abilities($request, $redirect),
        );
    }

    private function abilities(Request $request, ?Redirect $redirect): RedirectAbilitiesData
    {
        $user = $request->user();

        return new RedirectAbilitiesData(
            create: $user?->can('create', Redirect::class) ?? false,
            import: $user?->can('import', Redirect::class) ?? false,
            delete: $redirect !== null
                ? ($user?->can('delete', $redirect) ?? false)
                : ($user?->isAdmin() ?? false),
        );
    }
}
