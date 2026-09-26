<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Data\Content\PageTranslationInputData;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\User;
use App\Services\Content\PageSlugRedirects;
use App\Services\Content\RichTextRenderer;
use Illuminate\Support\Facades\DB;

/**
 * Create a page with its initial translations in one transaction, together
 * with its audit entries (`page.created`, and `page.published` when any
 * translation starts out published).
 */
class CreatePage
{
    public function __construct(
        private readonly RichTextRenderer $richText,
        private readonly PageSlugRedirects $redirects,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, PageTranslationInputData>  $translations  Keyed by locale.
     */
    public function handle(User $actor, array $translations): Page
    {
        return DB::transaction(function () use ($actor, $translations): Page {
            $page = Page::query()->create([
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $changes = [];
            $published = [];

            foreach ($translations as $locale => $input) {
                $this->redirects->claim($locale, $input->slug);

                $page->translations()->create([
                    'locale' => $locale,
                    'title' => $input->title,
                    'slug' => $input->slug,
                    'meta_description' => $input->metaDescription,
                    'body' => $input->body === null ? null : $this->richText->sanitize($input->body),
                    'status' => $input->status,
                    'published_at' => $input->status === PublicationStatus::Published ? now() : null,
                ]);

                $changes["{$locale}.title"] = RecordAuditEvent::change(null, $input->title);
                $changes["{$locale}.slug"] = RecordAuditEvent::change(null, $input->slug);
                $changes["{$locale}.status"] = RecordAuditEvent::change(null, $input->status->value);

                if ($input->body !== null) {
                    $changes["{$locale}.body"] = RecordAuditEvent::redacted();
                }

                if ($input->status === PublicationStatus::Published) {
                    $published["{$locale}.status"] = RecordAuditEvent::change(null, PublicationStatus::Published->value);
                }
            }

            $this->audit->handle(AuditAction::PageCreated, $page, $actor, $changes);

            if ($published !== []) {
                $this->audit->handle(AuditAction::PagePublished, $page, $actor, $published);
            }

            return $page;
        });
    }
}
