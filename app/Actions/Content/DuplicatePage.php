<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\User;
use App\Services\Content\CopySlugs;
use Illuminate\Support\Facades\DB;

/**
 * Copy a page with every translation as a new draft page: each translation
 * gets a free `-copy-N` slug in its locale, the draft status and no
 * publication date, so the copy stays invisible to visitors until it is
 * published. Slug redirects of the source are not copied and existing
 * redirects are left untouched. The source is not modified; the audit entry
 * `page.duplicated` names it.
 */
class DuplicatePage
{
    public function __construct(
        private readonly CopySlugs $copySlugs,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Page $source, User $actor): Page
    {
        return DB::transaction(function () use ($source, $actor): Page {
            $copy = Page::query()->create([
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $changes = ['duplicated_from' => RecordAuditEvent::change(null, $source->id)];

            foreach ($source->translations()->orderBy('id')->get() as $translation) {
                $slug = $this->copySlugs->next('page_translations', 'page_slug_redirects', $translation->locale, $translation->slug);

                $copy->translations()->create([
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'slug' => $slug,
                    'meta_description' => $translation->meta_description,
                    'body' => $translation->body,
                    'status' => PublicationStatus::Draft,
                    'published_at' => null,
                ]);

                $changes["{$translation->locale}.slug"] = RecordAuditEvent::change(null, $slug);
            }

            $this->audit->handle(AuditAction::PageDuplicated, $copy, $actor, $changes);

            return $copy;
        });
    }
}
