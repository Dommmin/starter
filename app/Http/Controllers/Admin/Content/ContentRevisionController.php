<?php

namespace App\Http\Controllers\Admin\Content;

use App\Actions\Content\RestoreContentRevision;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\User;
use App\Services\Content\ContentSnapshots;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Restore an earlier version of a page or article. The route checks
 * `update`; changing a published record also requires `publish`.
 */
class ContentRevisionController extends Controller
{
    public function __construct(
        private readonly ContentSnapshots $snapshots,
        private readonly RestoreContentRevision $restore,
    ) {}

    public function page(Request $request, Page $page, int $revision): RedirectResponse
    {
        $this->restore($request, $page, $revision);

        return to_route('admin.pages.edit', $page);
    }

    public function article(Request $request, Article $article, int $revision): RedirectResponse
    {
        $this->restore($request, $article, $revision);

        return to_route('admin.articles.edit', $article);
    }

    private function restore(Request $request, Page|Article $record, int $revisionId): void
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ContentRevision $revision */
        $revision = $this->snapshots->query($record)->findOrFail($revisionId);

        $isPublished = $record->translations()->get()->contains(
            static fn ($translation): bool => $translation->isPublished(),
        );

        abort_if($isPublished && ! $user->can('publish', $record::class), 403);

        $this->restore->handle($record, $revision, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.revisions.restored')]);
    }
}
