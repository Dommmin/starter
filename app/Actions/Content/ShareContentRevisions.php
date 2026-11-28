<?php

namespace App\Actions\Content;

use App\Data\Admin\Revisions\RevisionListItemData;
use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use App\Services\Content\ContentSnapshots;
use Inertia\Inertia;

/**
 * Share the earlier versions of the edited record as the `revisions` page
 * prop, only for users who may change it.
 */
class ShareContentRevisions
{
    public function __construct(private readonly ContentSnapshots $snapshots) {}

    public function handle(?User $user, Page|Article $record): void
    {
        if ($user === null || ! $user->can('update', $record)) {
            return;
        }

        Inertia::share('revisions', array_values($this->snapshots->query($record)
            ->with('author')
            ->orderByDesc('id')
            ->limit(ContentSnapshots::KEEP)
            ->get()
            ->map(RevisionListItemData::fromRevision(...))
            ->all()));
    }
}
