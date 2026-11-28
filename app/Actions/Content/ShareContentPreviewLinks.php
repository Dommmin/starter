<?php

namespace App\Actions\Content;

use App\Data\Admin\PreviewLinks\PreviewLinkListItemData;
use App\Models\Article;
use App\Models\ContentPreviewLink;
use App\Models\Page;
use App\Models\User;
use Inertia\Inertia;

/**
 * Share the active share links of the edited record as the `previewLinks`
 * page prop, only for users who may change it.
 */
class ShareContentPreviewLinks
{
    public function handle(?User $user, Page|Article $record): void
    {
        if ($user === null || ! $user->can('update', $record)) {
            return;
        }

        Inertia::share('previewLinks', array_values(ContentPreviewLink::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->id)
            ->where('expires_at', '>', now())
            ->with('creator')
            ->orderByDesc('id')
            ->get()
            ->map(PreviewLinkListItemData::fromModel(...))
            ->all()));
    }
}
