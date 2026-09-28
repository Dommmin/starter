<?php

namespace App\Support\DemoContent;

use App\Actions\Content\DeletePage;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\DemoContent;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample pages of {@see DemoContent::PAGES}: a page matches only when its
 * translations have exactly the seeded locales and slugs.
 */
class DemoPages implements DemoContentProvider
{
    use MatchesSeededTranslations;

    public function __construct(private readonly DeletePage $deletePage) {}

    public function label(): string
    {
        return 'pages';
    }

    public function records(): array
    {
        return $this->matchingRecords(Page::query(), DemoContent::PAGES);
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        return $this->wasModified($this->page($record));
    }

    public function describe(Model $record): string
    {
        return $this->slugs($this->page($record));
    }

    public function delete(Model $record, User $actor): void
    {
        $this->deletePage->handle($this->page($record), $actor);
    }

    private function page(Model $record): Page
    {
        if (! $record instanceof Page) {
            throw new InvalidArgumentException('Expected a page.');
        }

        return $record;
    }
}
