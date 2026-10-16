<?php

namespace App\Data\Content;

use App\Models\Tag;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Tag of the public article list: its name in the locale and the URL of
 * the list filtered by it.
 */
#[TypeScript]
class PublicTagData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $url,
    ) {}

    public static function fromTag(Tag $tag, string $locale, string $fallback, string $listUrl): self
    {
        return new self(
            slug: $tag->slug,
            name: $tag->nameIn($locale, $fallback),
            url: $listUrl.'?'.http_build_query(['tag' => $tag->slug]),
        );
    }

    /**
     * @param  iterable<Tag>  $tags
     * @return list<self>
     */
    public static function listOf(iterable $tags, string $locale, string $fallback, string $listUrl): array
    {
        $items = [];

        foreach ($tags as $tag) {
            $items[] = self::fromTag($tag, $locale, $fallback, $listUrl);
        }

        return $items;
    }
}
