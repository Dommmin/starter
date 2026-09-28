<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Data\Content\ArticleTranslationInputData;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\User;
use App\Services\Content\ArticleSlugRedirects;
use App\Services\Content\RichTextRenderer;
use Illuminate\Support\Facades\DB;

/**
 * Create an article with its cover and initial translations in one
 * transaction, together with its audit entries (`article.created`, and
 * `article.published` when any translation starts out published).
 */
class CreateArticle
{
    public function __construct(
        private readonly RichTextRenderer $richText,
        private readonly ArticleSlugRedirects $redirects,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, ArticleTranslationInputData>  $translations  Keyed by locale.
     */
    public function handle(User $actor, ?int $coverMediaId, array $translations): Article
    {
        return DB::transaction(function () use ($actor, $coverMediaId, $translations): Article {
            $article = Article::query()->create([
                'cover_media_id' => $coverMediaId,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $changes = [];
            $published = [];

            if ($coverMediaId !== null) {
                $changes['cover_media_id'] = RecordAuditEvent::change(null, $coverMediaId);
            }

            foreach ($translations as $locale => $input) {
                $this->redirects->claim($locale, $input->slug);

                $publishedAt = $input->resolvePublishedAt(null);

                $article->translations()->create([
                    'locale' => $locale,
                    'title' => $input->title,
                    'slug' => $input->slug,
                    'excerpt' => $input->excerpt,
                    'meta_description' => $input->metaDescription,
                    'body' => $input->body === null ? null : $this->richText->sanitize($input->body),
                    'status' => $input->status,
                    'published_at' => $publishedAt,
                ]);

                $changes["{$locale}.title"] = RecordAuditEvent::change(null, $input->title);
                $changes["{$locale}.slug"] = RecordAuditEvent::change(null, $input->slug);
                $changes["{$locale}.status"] = RecordAuditEvent::change(null, $input->status->value);

                if ($publishedAt !== null) {
                    $changes["{$locale}.published_at"] = RecordAuditEvent::change(null, $publishedAt->toIso8601String());
                }

                if ($input->body !== null) {
                    $changes["{$locale}.body"] = RecordAuditEvent::redacted();
                }

                if ($input->status === PublicationStatus::Published) {
                    $published["{$locale}.status"] = RecordAuditEvent::change(null, PublicationStatus::Published->value);
                }
            }

            $this->audit->handle(AuditAction::ArticleCreated, $article, $actor, $changes);

            if ($published !== []) {
                $this->audit->handle(AuditAction::ArticlePublished, $article, $actor, $published);
            }

            return $article;
        });
    }
}
