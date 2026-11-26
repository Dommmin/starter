<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\User;
use App\Services\Content\CopySlugs;
use Illuminate\Support\Facades\DB;

/**
 * Copy an article with its cover, tags and every translation as a new draft
 * article: each translation gets a free `-copy-N` slug in its locale, the
 * draft status and no publication date, so the copy stays invisible to
 * visitors until it is published. Slug redirects of the source are not
 * copied and existing redirects are left untouched. The source is not
 * modified; the audit entry `article.duplicated` names it.
 */
class DuplicateArticle
{
    public function __construct(
        private readonly CopySlugs $copySlugs,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Article $source, User $actor): Article
    {
        return DB::transaction(function () use ($source, $actor): Article {
            $copy = Article::query()->create([
                'cover_media_id' => $source->cover_media_id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $copy->tags()->sync($source->tags()->allRelatedIds());

            $changes = ['duplicated_from' => RecordAuditEvent::change(null, $source->id)];

            foreach ($source->translations()->orderBy('id')->get() as $translation) {
                $slug = $this->copySlugs->next('article_translations', 'article_slug_redirects', $translation->locale, $translation->slug);

                $copy->translations()->create([
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'slug' => $slug,
                    'excerpt' => $translation->excerpt,
                    'meta_description' => $translation->meta_description,
                    'body' => $translation->body,
                    'status' => PublicationStatus::Draft,
                    'published_at' => null,
                ]);

                $changes["{$translation->locale}.slug"] = RecordAuditEvent::change(null, $slug);
            }

            $this->audit->handle(AuditAction::ArticleDuplicated, $copy, $actor, $changes);

            return $copy;
        });
    }
}
