<?php

namespace App\Http\Controllers\Admin\Content;

use App\Actions\Content\CreateContentPreviewLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentPreviewLinkRequest;
use App\Models\Article;
use App\Models\ContentPreviewLink;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Create and revoke share links of a page or article. The routes check `update`.
 */
class ContentPreviewLinkController extends Controller
{
    // Separate actions per type: the typed parameter makes the route bind the
    // model before the `can` middleware reads it.
    public function storeForPage(StoreContentPreviewLinkRequest $request, Page $page, CreateContentPreviewLink $createLink): RedirectResponse
    {
        return $this->store($request, $page, $createLink);
    }

    public function storeForArticle(StoreContentPreviewLinkRequest $request, Article $article, CreateContentPreviewLink $createLink): RedirectResponse
    {
        return $this->store($request, $article, $createLink);
    }

    private function store(StoreContentPreviewLinkRequest $request, Page|Article $record, CreateContentPreviewLink $createLink): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $url = $createLink->handle(
            $record,
            $request->string('locale')->toString(),
            $request->integer('days'),
            $user,
        );

        // Flashed once: the token cannot be read again later.
        Inertia::flash('sharedPreviewUrl', $url);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.previewLinks.created')]);

        return back();
    }

    public function destroyForPage(Page $page, int $previewLink): RedirectResponse
    {
        return $this->destroy($page, $previewLink);
    }

    public function destroyForArticle(Article $article, int $previewLink): RedirectResponse
    {
        return $this->destroy($article, $previewLink);
    }

    private function destroy(Page|Article $record, int $linkId): RedirectResponse
    {
        ContentPreviewLink::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->id)
            ->findOrFail($linkId)
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.previewLinks.revoked')]);

        return back();
    }
}
