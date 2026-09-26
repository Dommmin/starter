<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete a page with its translations and slug redirects
 * (FK cascade), recording `page.deleted` in the same transaction.
 */
class DeletePage
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(Page $page, User $actor): void
    {
        DB::transaction(function () use ($page, $actor): void {
            $changes = [];
            foreach ($page->translations()->orderBy('locale')->get() as $translation) {
                $changes["{$translation->locale}.title"] = RecordAuditEvent::change($translation->title, null);
                $changes["{$translation->locale}.slug"] = RecordAuditEvent::change($translation->slug, null);
            }

            $this->audit->handle(AuditAction::PageDeleted, $page, $actor, $changes);

            $page->delete();
        });
    }
}
