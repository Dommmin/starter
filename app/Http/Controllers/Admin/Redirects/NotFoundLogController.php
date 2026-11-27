<?php

namespace App\Http\Controllers\Admin\Redirects;

use App\Data\Admin\Redirects\NotFoundLogIndexData;
use App\Data\Admin\Redirects\NotFoundLogItemData;
use App\Http\Controllers\Controller;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin monitor of top 404 paths recorded on public pages.
 */
class NotFoundLogController extends Controller
{
    public function index(Request $request): Response
    {
        $items = array_values(
            NotFoundLog::query()
                ->orderByDesc('hits')
                ->orderByDesc('last_seen_at')
                ->limit(100)
                ->get()
                ->map(static fn (NotFoundLog $log): NotFoundLogItemData => NotFoundLogItemData::fromModel($log))
                ->all(),
        );

        return Inertia::render('admin/redirects/not-found', new NotFoundLogIndexData(
            items: $items,
            canCreateRedirect: $request->user()?->can('create', Redirect::class) ?? false,
        ));
    }

    public function destroy(NotFoundLog $notFoundLog): RedirectResponse
    {
        $notFoundLog->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('admin.redirects.notFound.dismissed'),
        ]);

        return back();
    }
}
