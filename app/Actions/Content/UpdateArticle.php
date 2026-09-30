<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Data\Content\ArticleTranslationInputData;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\User;
use App\Services\Content\ArticleSlugRedirects;
use App\Services\Content\RichTextRenderer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update the cover and translations of an article with optimistic locking.
 *
 * Active locales present in the input are created or updated; active locales
 * missing from the input lose their translation. `published_at` is resolved
 * by ArticleTranslationInputData::resolvePublishedAt() and is kept when a
 * translation returns to draft.
 *
 * A slug change of a translation that has ever been visible keeps the old
 * slug as a permanent redirect. Content changes are audited as
 * `article.updated`, status changes as `article.published` /
 * `article.unpublished`, all in the same transaction.
 */
class UpdateArticle
{
    /**
     * Translation fields whose old and new values are safe to store in the
     * audit log. The body is audited only as "changed".
     */
    private const array AUDITED_FIELDS = ['title', 'slug', 'excerpt', 'meta_description'];

    public function __construct(
        private readonly RichTextRenderer $richText,
        private readonly ArticleSlugRedirects $redirects,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, ArticleTranslationInputData>  $translations  Keyed by locale.
     * @param  list<string>  $activeLocales  Locales managed by the form.
     * @param  string  $expectedUpdatedAt  Article version the form was loaded with.
     *
     * @throws ValidationException When the article changed in the meantime (`conflict`).
     */
    public function handle(
        Article $article,
        User $actor,
        ?int $coverMediaId,
        array $translations,
        array $activeLocales,
        string $expectedUpdatedAt,
    ): Article {
        return DB::transaction(function () use ($article, $actor, $coverMediaId, $translations, $activeLocales, $expectedUpdatedAt): Article {
            $locked = Article::query()->whereKey($article->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.articles.conflict'),
                ]);
            }

            $existing = $locked->translations()->get()->keyBy('locale');

            $changes = [];
            $published = [];
            $unpublished = [];

            if ($locked->cover_media_id !== $coverMediaId) {
                $changes['cover_media_id'] = RecordAuditEvent::change($locked->cover_media_id, $coverMediaId);
                $locked->cover_media_id = $coverMediaId;
            }

            foreach ($activeLocales as $locale) {
                $input = $translations[$locale] ?? null;
                /** @var ArticleTranslation|null $translation */
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
                $wasVisible = $wasPublished && $this->isPast($translation->published_at);
                $formerPublishedAt = $translation->published_at;

                $translation->fill([
                    'title' => $input->title,
                    'slug' => $input->slug,
                    'excerpt' => $input->excerpt,
                    'meta_description' => $input->metaDescription,
                    'body' => $input->body === null ? null : $this->richText->sanitize($input->body),
                    'status' => $input->status,
                    'published_at' => $input->resolvePublishedAt($formerPublishedAt),
                ]);

                foreach (self::AUDITED_FIELDS as $field) {
                    if (! $translation->exists || $translation->isDirty($field)) {
                        $changes["{$locale}.{$field}"] = RecordAuditEvent::change(
                            $translation->exists ? $translation->getOriginal($field) : null,
                            $translation->getAttribute($field),
                        );
                    }
                }

                if ($formerPublishedAt?->getTimestamp() !== $translation->published_at?->getTimestamp()) {
                    $changes["{$locale}.published_at"] = RecordAuditEvent::change(
                        $formerPublishedAt?->toIso8601String(),
                        $translation->published_at?->toIso8601String(),
                    );
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

                if ($slugChanged && is_string($formerSlug) && $wasVisible) {
                    $this->redirects->rememberFormerSlug($translation, $formerSlug);
                }
            }

            if ($changes !== []) {
                $this->audit->handle(AuditAction::ArticleUpdated, $locked, $actor, $changes);
            }

            if ($published !== []) {
                $this->audit->handle(AuditAction::ArticlePublished, $locked, $actor, $published);
            }

            if ($unpublished !== []) {
                $this->audit->handle(AuditAction::ArticleUnpublished, $locked, $actor, $unpublished);
            }

            $locked->forceFill([
                'updated_by' => $actor->id,
                'updated_at' => $locked->freshTimestamp(),
            ])->save();

            return $locked;
        });
    }

    private function isPast(?CarbonInterface $moment): bool
    {
        return $moment !== null && $moment->lessThanOrEqualTo(now());
    }
}
