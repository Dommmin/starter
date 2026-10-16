<?php

namespace App\Support\DemoContent;

use App\Actions\Tags\SaveTag;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample tags of {@see TagSeeder}, matched by slug; edited when renamed
 * after creation. Deleted through the audited SaveTag action, which removes
 * them from every article.
 */
class DemoTags implements DemoContentProvider
{
    public function __construct(private readonly SaveTag $saveTag) {}

    public function label(): string
    {
        return 'tags';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return false;
    }

    public function records(): array
    {
        return array_values(Tag::query()
            ->whereIn('slug', array_keys(TagSeeder::TAGS))
            ->orderBy('slug')
            ->get()
            ->all());
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        $tag = $this->tag($record);

        return $tag->updated_at !== null
            && $tag->created_at !== null
            && $tag->updated_at->gt($tag->created_at);
    }

    public function describe(Model $record): string
    {
        return $this->tag($record)->slug;
    }

    public function delete(Model $record, User $actor): void
    {
        $this->saveTag->delete($this->tag($record), $actor);
    }

    private function tag(Model $record): Tag
    {
        if (! $record instanceof Tag) {
            throw new InvalidArgumentException('Expected a tag.');
        }

        return $record;
    }
}
