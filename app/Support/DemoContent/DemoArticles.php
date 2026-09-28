<?php

namespace App\Support\DemoContent;

use App\Actions\Content\DeleteArticle;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\Article;
use App\Models\User;
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

    public function __construct(private readonly DeleteArticle $deleteArticle) {}

    public function label(): string
    {
        return 'articles';
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
        $this->deleteArticle->handle($this->article($record), $actor);
    }

    private function article(Model $record): Article
    {
        if (! $record instanceof Article) {
            throw new InvalidArgumentException('Expected an article.');
        }

        return $record;
    }
}
