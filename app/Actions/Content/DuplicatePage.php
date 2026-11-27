<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Services\Content\PageSlugRedirects;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clone an existing page with all its translations as drafts with unique
 * `{slug}-copy` slugs, recording a `page.created` audit entry.
 */
class DuplicatePage
{
    public function __construct(
        private readonly PageSlugRedirects $redirects,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Page $source): Page
    {
        return DB::transaction(function () use ($actor, $source): Page {
            $source->loadMissing('translations');

            $page = Page::query()->create([
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $changes = ['duplicated_from' => RecordAuditEvent::change(null, $source->id)];

            foreach ($source->translations as $translation) {
                $slug = $this->uniqueSlug($translation->locale, $translation->slug);
                $title = Str::limit($translation->title.' (Copy)', 160, '');

                $this->redirects->claim($translation->locale, $slug);

                $page->translations()->create([
                    'locale' => $translation->locale,
                    'title' => $title,
                    'slug' => $slug,
                    'meta_description' => $translation->meta_description,
                    'body' => $translation->body,
                    'status' => PublicationStatus::Draft,
                    'published_at' => null,
                ]);

                $changes["{$translation->locale}.title"] = RecordAuditEvent::change(null, $title);
                $changes["{$translation->locale}.slug"] = RecordAuditEvent::change(null, $slug);
                $changes["{$translation->locale}.status"] = RecordAuditEvent::change(null, PublicationStatus::Draft->value);
            }

            $this->audit->handle(AuditAction::PageCreated, $page, $actor, $changes);

            return $page;
        });
    }

    private function uniqueSlug(string $locale, string $baseSlug): string
    {
        $candidate = Str::limit($baseSlug, 150, '').'-copy';
        $counter = 2;

        while (PageTranslation::query()->where('locale', $locale)->where('slug', $candidate)->exists()) {
            $suffix = "-copy-{$counter}";
            $candidate = Str::limit($baseSlug, 160 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $candidate;
    }
}
