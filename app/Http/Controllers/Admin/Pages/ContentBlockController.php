<?php

namespace App\Http\Controllers\Admin\Pages;

use App\Actions\Content\ContentBlocks;
use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Shared blocks, managed from the sections screen of a page. Every route
 * checks `update` on that page.
 */
class ContentBlockController extends Controller
{
    public function __construct(private readonly ContentBlocks $blocks) {}

    public function share(Request $request, Page $page, PageSection $pageSection): RedirectResponse
    {
        abort_if($pageSection->page_id !== $page->id || $pageSection->content_block_id !== null, 404);

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $this->blocks->share($page, $pageSection, (string) $validated['name'], $this->actor($request));

        return $this->done('shared');
    }

    public function insert(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate(['content_block_id' => ['required', 'integer', 'exists:content_blocks,id']]);

        $this->blocks->insert($page, ContentBlock::query()->findOrFail((int) $validated['content_block_id']), $this->actor($request));

        return $this->done('inserted');
    }

    public function detach(Request $request, Page $page, PageSection $pageSection): RedirectResponse
    {
        abort_if($pageSection->page_id !== $page->id, 404);

        $this->blocks->detach($page, $pageSection, $this->actor($request));

        return $this->done('detached');
    }

    public function destroy(Request $request, Page $page, ContentBlock $contentBlock): RedirectResponse
    {
        $this->blocks->delete($contentBlock, $this->actor($request));

        return $this->done('deleted');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __("admin.pages.blocks.{$message}")]);

        return back();
    }
}
