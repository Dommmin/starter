<?php

namespace App\Data\Navigation;

use App\Enums\MenuItemType;
use Spatie\LaravelData\Data;

/**
 * Validated editable fields of a menu item (MenuItemInputRules). Fields
 * that do not apply to the type are null; `openInNewTab` is only ever true
 * for external links.
 */
class MenuItemInputData extends Data
{
    public function __construct(
        public ?int $parentId,
        public MenuItemType $type,
        public ?int $pageId,
        public ?int $articleId,
        public ?string $anchor,
        public ?string $url,
        public ?string $label,
        public bool $openInNewTab,
    ) {}

    /**
     * Column values of the menu_items row (without menu, position and authors).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => $this->parentId,
            'type' => $this->type,
            'page_id' => $this->pageId,
            'article_id' => $this->articleId,
            'anchor' => $this->anchor,
            'url' => $this->url,
            'label' => $this->label,
            'open_in_new_tab' => $this->openInNewTab,
        ];
    }
}
