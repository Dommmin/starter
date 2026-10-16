<?php

namespace App\Data\Admin\Tags;

use App\Models\Tag;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state of the tag editor. `updatedAt` is null for a new tag and is
 * sent back on update (optimistic locking).
 */
#[TypeScript]
class TagFormData extends Data
{
    /**
     * @param  array<string, string>  $name  Keyed by public locale.
     */
    public function __construct(
        public ?int $id,
        public ?string $updatedAt,
        public string $slug,
        public array $name,
    ) {}

    /**
     * @param  list<string>  $locales
     */
    public static function blank(array $locales): self
    {
        return new self(
            id: null,
            updatedAt: null,
            slug: '',
            name: array_fill_keys($locales, ''),
        );
    }

    /**
     * @param  list<string>  $locales
     */
    public static function fromModel(Tag $tag, array $locales): self
    {
        return new self(
            id: $tag->id,
            updatedAt: $tag->updated_at?->toIso8601String(),
            slug: $tag->slug,
            name: array_combine($locales, array_map(fn (string $locale): string => $tag->name[$locale] ?? '', $locales)),
        );
    }
}
