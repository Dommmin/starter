<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Data\Content\PageTranslationInputData;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Services\Content\PageSlugRedirects;
use App\Services\Content\RichTextRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update the translations of a page with optimistic locking.
 *
 * Active locales present in the input are created or updated; active locales
 * missing from the input lose their translation. `published_at` records the
 * first publication and is kept when a translation returns to draft.
 *
 * A slug change of a translation that has ever been published keeps the old
 * slug as a permanent redirect (see PageSlugRedirects). Content changes are
 * audited as `page.updated`, status changes as `page.published` /
 * `page.unpublished`, all in the same transaction.
 */
class UpdatePage
{
    /**
     * Translation fields whose old and new values are safe to store in the
     * audit log. The body is audited only as "changed".
     */
    private const array AUDITED_FIELDS = ['title', 'slug', 'meta_description'];

    public function __construct(
        private readonly RichTextRenderer $richText,
        private readonly PageSlugRedirects $redirects,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, PageTranslationInputData>  $translations  Keyed by locale.
     * @param  list<string>  $activeLocales  Locales managed by the form.
     * @param  string  $expectedUpdatedAt  Page version the form was loaded with.
     *
     * @throws ValidationException When the page changed in the meantime (`conflict`).
     */
    public function handle(Page $page, User $actor, array $translations, array $activeLocales, string $expectedUpdatedAt): Page
    {
        return DB::transaction(function () use ($page, $actor, $translations, $activeLocales, $expectedUpdatedAt): Page {
            $locked = Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.pages.conflict'),
                ]);
            }

            $existing = $locked->translations()->get()->keyBy('locale');

            $changes = [];
            $published = [];
            $unpublished = [];

            foreach ($activeLocales as $locale) {
                $input = $translations[$locale] ?? null;
                /** @var PageTranslation|null $translation */
                $translation = $existing->get($locale);
                $wasPublished = $translation?->isPublished() ?? false;

                if ($input === null) {
                    if ($translation !== null) {
                        $changes["{$locale}.title"] = RecordAuditEvent::change($translation->title, null);
                        $changes["{$locale}.slug"] = RecordAuditEvent::change($translation->slug, null);

                        if ($wasPublished) {
                            $unpublished["{$locale}.status"] = RecordAuditEvent::change(PublicationStatus::Published->value, null);
                        }

                        $translation->delete();
                    }

                    continue;
                }

                $translation ??= $locked->translations()->make(['locale' => $locale]);
                $formerSlug = $translation->exists ? $translation->getOriginal('slug') : null;
                $everPublished = $translation->published_at !== null;

                $translation->fill([
                    'title' => $input->title,
                    'slug' => $input->slug,
                    'meta_description' => $input->metaDescription,
                    'body' => $input->body === null ? null : $this->richText->sanitize($input->body),
                    'status' => $input->status,
                ]);

                if ($input->status === PublicationStatus::Published && $translation->published_at === null) {
                    $translation->published_at = now();
                }

                foreach (self::AUDITED_FIELDS as $field) {
                    if (! $translation->exists || $translation->isDirty($field)) {
                        $changes["{$locale}.{$field}"] = RecordAuditEvent::change(
                            $translation->exists ? $translation->getOriginal($field) : null,
                            $translation->getAttribute($field),
                        );
                    }
                }

                if ($translation->isDirty('body') && ($translation->exists || $translation->body !== null)) {
                    $changes["{$locale}.body"] = RecordAuditEvent::redacted();
                }

                $isPublished = $input->status === PublicationStatus::Published;
                if ($isPublished !== $wasPublished) {
                    $statusChange = RecordAuditEvent::change(
                        $translation->exists ? $translation->getOriginal('status')?->value : null,
                        $input->status->value,
                    );

                    if ($isPublished) {
                        $published["{$locale}.status"] = $statusChange;
                    } else {
                        $unpublished["{$locale}.status"] = $statusChange;
                    }
                }

                $slugChanged = ! $translation->exists || $translation->isDirty('slug');

                if ($slugChanged) {
                    $this->redirects->claim($locale, $input->slug);
                }

                $translation->save();

                if ($slugChanged && is_string($formerSlug) && $everPublished) {
                    $this->redirects->rememberFormerSlug($translation, $formerSlug);
                }
            }

            if ($changes !== []) {
                $this->audit->handle(AuditAction::PageUpdated, $locked, $actor, $changes);
            }

            if ($published !== []) {
                $this->audit->handle(AuditAction::PagePublished, $locked, $actor, $published);
            }

            if ($unpublished !== []) {
                $this->audit->handle(AuditAction::PageUnpublished, $locked, $actor, $unpublished);
            }

            $locked->forceFill([
                'updated_by' => $actor->id,
                'updated_at' => $locked->freshTimestamp(),
            ])->save();

            return $locked;
        });
    }
}
