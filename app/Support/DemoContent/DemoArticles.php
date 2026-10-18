<?php

namespace App\Support\DemoContent;

use App\Actions\Content\DeleteArticle;
use App\Actions\Content\ManageTrashedContent;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Enums\TrashedContentType;
use App\Models\Article;
use App\Models\User;
use App\Repositories\Content\TrashRepository;
use Database\Seeders\DemoContent;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample articles of {@see DemoContent::ARTICLES}: an article matches only
 * when its translations have exactly the seeded locales and slugs.
 */
class DemoArticles implements DemoContentProvider
{
    use MatchesSeededTranslations;

    public function __construct(
        private readonly DeleteArticle $deleteArticle,
        private readonly ManageTrashedContent $trash,
        private readonly TrashRepository $trashed,
    ) {}

    public function label(): string
    {
        return 'articles';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return false;
    }

    public function records(): array
    {
        return $this->matchingRecords(Article::query(), DemoContent::ARTICLES);
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        return $this->wasModified($this->article($record));
    }

    public function describe(Model $record): string
    {
        return $this->slugs($this->article($record));
    }

    public function delete(Model $record, User $actor): void
    {
        $article = $this->article($record);

        // Audited move to the trash, then removed for good: sample content
        // must not keep its slugs taken for the trash retention period.
        $this->deleteArticle->handle($article, $actor);
        $this->trash->purge($this->trashed->findTrashed(TrashedContentType::Articles, $article->id) ?? $article, $actor);
    }

    private function article(Model $record): Article
    {
        if (! $record instanceof Article) {
            throw new InvalidArgumentException('Expected an article.');
        }

        return $record;
    }
}
