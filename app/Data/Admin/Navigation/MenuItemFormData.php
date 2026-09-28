<?php

namespace App\Data\Admin\Navigation;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state for creating or editing a menu item. Location and locale are
 * fixed once the item exists; `updatedAt` must be sent back on update for
 * optimistic locking.
 */
#[TypeScript]
class MenuItemFormData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $updatedAt,
        public MenuLocation $location,
        public string $locale,
        public ?int $parentId,
        public MenuItemType $type,
        public ?int $pageId,
        public ?int $articleId,
        public ?string $anchor,
        public ?string $url,
        public ?string $label,
        public bool $openInNewTab,
        public bool $targetMissing,
        public bool $draftTarget,
        public bool $hasChildren,
    ) {}

    public static function blank(MenuLocation $location, string $locale): self
    {
        return new self(
            id: null,
            updatedAt: null,
            location: $location,
            locale: $locale,
            parentId: null,
            type: MenuItemType::Page,
            pageId: null,
            articleId: null,
            anchor: null,
            url: null,
            label: null,
            openInNewTab: false,
            targetMissing: false,
            draftTarget: false,
            hasChildren: false,
        );
    }

    /**
     * Requires the target translations loaded (MenuItemRepository::forEditor).
     */
    public static function fromModel(MenuItem $item, bool $hasChildren): self
    {
        $target = MenuItemTargetState::of($item);

        return new self(
            id: $item->id,
            updatedAt: $item->updated_at?->toIso8601String(),
            location: $item->location,
            locale: $item->locale,
            parentId: $item->parent_id,
            type: $item->type,
            pageId: $item->page_id,
            articleId: $item->article_id,
            anchor: $item->anchor,
            url: $item->url,
            label: $item->label,
            openInNewTab: $item->open_in_new_tab,
            targetMissing: $target->missing,
            draftTarget: $target->hidden,
            hasChildren: $hasChildren,
        );
    }
}
