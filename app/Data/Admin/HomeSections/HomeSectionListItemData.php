<?php

namespace App\Data\Admin\HomeSections;

use App\Enums\HomeSectionAnchor;
use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin home section list. `title` is the section heading
 * (if the type has one) to tell the rows apart.
 */
#[TypeScript]
class HomeSectionListItemData extends Data
{
    public function __construct(
        public int $id,
        public HomeSectionType $type,
        public HomeSectionAnchor $anchor,
        public bool $enabled,
        public int $position,
        public ?string $title,
        public ?string $updatedAt,
    ) {}

    public static function fromModel(HomeSection $section): self
    {
        $title = $section->content->toArray()['title'] ?? null;

        return new self(
            id: $section->id,
            type: $section->type,
            anchor: $section->type->anchor(),
            enabled: $section->enabled,
            position: $section->position,
            title: is_string($title) ? $title : null,
            updatedAt: $section->updated_at?->toIso8601String(),
        );
    }
}
