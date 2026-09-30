<?php

namespace App\Observers;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use App\Models\Page;

/**
 * Menu anchors on a page are removed together with the page, inside the
 * same transaction (DeletePage). Otherwise the `nullOnDelete` foreign key
 * would turn them into home page anchors (`/#...`). Their child items are
 * removed by the `parent_id` cascade. Page and article links keep their
 * row with a nulled target and show "target deleted" in the panel.
 */
class PageMenuAnchorObserver
{
    public function deleting(Page $page): void
    {
        MenuItem::query()
            ->where('type', MenuItemType::Anchor->value)
            ->where('page_id', $page->id)
            ->delete();
    }
}
