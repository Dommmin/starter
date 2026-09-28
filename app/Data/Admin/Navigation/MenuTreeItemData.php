<?php

namespace App\Data\Admin\Navigation;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One item of the admin menu tree. `label` is the explicit label or the
 * target title in the menu locale (null when neither exists);
 * `targetMissing` marks a deleted page/article, `draftTarget` a target that
 * visitors cannot see in this locale (draft, scheduled or untranslated).
 */
#[TypeScript]
class MenuTreeItemData extends Data
{
    /**
     * @param  list<MenuTreeItemData>  $children
     */
    public function __construct(
        public int $id,
        public MenuItemType $type,
        public ?string $label,
        public ?string $anchor,
        public ?string $url,
        public bool $openInNewTab,
        public bool $targetMissing,
        public bool $draftTarget,
        public array $children,
    ) {}

    /**
     * Requires the target translations (and `children` for first-level
     * items) loaded by MenuItemRepository::adminTree().
     */
    public static function fromModel(MenuItem $item, bool $withChildren = true): self
    {
        $target = MenuItemTargetState::of($item);

        $children = [];
        if ($withChildren) {
            foreach ($item->children as $child) {
                $children[] = self::fromModel($child, false);
            }
        }

        return new self(
            id: $item->id,
            type: $item->type,
            label: $target->displayLabel($item),
            anchor: $item->anchor,
            url: $item->url,
            openInNewTab: $item->open_in_new_tab,
            targetMissing: $target->missing,
            draftTarget: $target->hidden,
            children: $children,
        );
    }
}
