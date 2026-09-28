<?php

namespace App\Data\Admin\Navigation;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A first-level item of the same menu that can receive children.
 */
#[TypeScript]
class MenuParentOptionData extends Data
{
    public function __construct(
        public int $id,
        public ?string $label,
        public MenuItemType $type,
    ) {}

    /**
     * Requires the target translations loaded by MenuItemRepository.
     */
    public static function fromModel(MenuItem $item): self
    {
        return new self(
            id: $item->id,
            label: MenuItemTargetState::of($item)->displayLabel($item),
            type: $item->type,
        );
    }
}
