<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete an article with its translations and slug redirects
 * (FK cascade), recording `article.deleted` in the same transaction. The
 * cover stays in the DAM.
 */
class DeleteArticle
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(Article $article, User $actor): void
    {
        DB::transaction(function () use ($article, $actor): void {
            $changes = [];
            foreach ($article->translations()->orderBy('locale')->get() as $translation) {
                $changes["{$translation->locale}.title"] = RecordAuditEvent::change($translation->title, null);
                $changes["{$translation->locale}.slug"] = RecordAuditEvent::change($translation->slug, null);
            }

            $this->audit->handle(AuditAction::ArticleDeleted, $article, $actor, $changes);

            $article->delete();
        });
    }
}
