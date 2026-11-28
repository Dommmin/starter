<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\TrashedContentType;
use App\Models\Article;
use App\Models\ContentPreviewLink;
use App\Models\Page;
use App\Models\User;
use App\Services\Content\ContentSnapshots;
use Illuminate\Support\Facades\DB;

/**
 * Restore a trashed page or article, or remove it for good together with
 * its translations, slugs and redirects (FK cascade). Both are audited in
 * the same transaction; a purge without an actor is the scheduled cleanup.
 */
class ManageTrashedContent
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly ContentSnapshots $snapshots,
    ) {}

    /**
     * Requires the `translations` relation loaded without the live-parent scope.
     */
    public function restore(Page|Article $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $record->restore();

            $this->audit->handle(TrashedContentType::of($record)->restoredAction(), $record, $actor, $this->titles($record, restoring: true));
        });
    }

    /**
     * Requires the `translations` relation loaded without the live-parent scope.
     */
    public function purge(Page|Article $record, ?User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $this->audit->handle(TrashedContentType::of($record)->purgedAction(), $record, $actor, $this->titles($record, restoring: false));

            $this->snapshots->forget($record);
            ContentPreviewLink::query()
                ->where('subject_type', $record->getMorphClass())
                ->where('subject_id', $record->id)
                ->delete();

            $record->forceDelete();
        });
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function titles(Page|Article $record, bool $restoring): array
    {
        $changes = [];
        foreach ($record->translations as $translation) {
            $changes["{$translation->locale}.title"] = $restoring
                ? RecordAuditEvent::change(null, $translation->title)
                : RecordAuditEvent::change($translation->title, null);
        }

        return $changes;
    }
}
