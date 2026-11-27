<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Tag;
use App\Models\User;
use App\Services\Content\ArticleSlugRedirects;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clone an existing article with its cover, tags and translations as drafts
 * with unique `{slug}-copy` slugs, recording an `article.created` audit entry.
 */
class DuplicateArticle
{
    public function __construct(
        private readonly ArticleSlugRedirects $redirects,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Article $source): Article
    {
        return DB::transaction(function () use ($actor, $source): Article {
            $source->loadMissing(['translations', 'tags']);

            $article = Article::query()->create([
                'cover_media_id' => $source->cover_media_id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $tagIds = array_values($source->tags->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
            if ($tagIds !== []) {
                $article->tags()->sync($tagIds);
            }

            $changes = ['duplicated_from' => RecordAuditEvent::change(null, $source->id)];
            if ($tagIds !== []) {
                $changes['tags'] = RecordAuditEvent::change(null, Tag::slugsOf($tagIds));
            }

            foreach ($source->translations as $translation) {
                $slug = $this->uniqueSlug($translation->locale, $translation->slug);
                $title = Str::limit($translation->title.' (Copy)', 160, '');

                $this->redirects->claim($translation->locale, $slug);

                $article->translations()->create([
                    'locale' => $translation->locale,
                    'title' => $title,
                    'slug' => $slug,
                    'excerpt' => $translation->excerpt,
                    'meta_description' => $translation->meta_description,
                    'body' => $translation->body,
                    'status' => PublicationStatus::Draft,
                    'published_at' => null,
                ]);

                $changes["{$translation->locale}.title"] = RecordAuditEvent::change(null, $title);
                $changes["{$translation->locale}.slug"] = RecordAuditEvent::change(null, $slug);
                $changes["{$translation->locale}.status"] = RecordAuditEvent::change(null, PublicationStatus::Draft->value);
            }

            $this->audit->handle(AuditAction::ArticleCreated, $article, $actor, $changes);

            return $article;
        });
    }

    private function uniqueSlug(string $locale, string $baseSlug): string
    {
        $candidate = Str::limit($baseSlug, 150, '').'-copy';
        $counter = 2;

        while (ArticleTranslation::query()->where('locale', $locale)->where('slug', $candidate)->exists()) {
            $suffix = "-copy-{$counter}";
            $candidate = Str::limit($baseSlug, 160 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $candidate;
    }
}
