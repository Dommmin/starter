<?php

namespace App\Data\Admin\Tags;

use App\Models\Tag;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row of the tag list. Requires `articles_count`.
 */
#[TypeScript]
class TagListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public int $articlesCount,
    ) {}

    public static function fromModel(Tag $tag, string $locale, string $fallback): self
    {
        return new self(
            id: $tag->id,
            slug: $tag->slug,
            name: $tag->nameIn($locale, $fallback),
            articlesCount: (int) $tag->getAttribute('articles_count'),
        );
    }
}
