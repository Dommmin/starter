<?php

namespace App\Data\Admin\Navigation;

use App\Data\Content\ContentLocalesData;
use App\Enums\MenuLocation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/navigation/index` screen: the whole tree of the
 * selected menu (location and locale), without pagination.
 */
#[TypeScript]
class MenuIndexData extends Data
{
    /**
     * @param  list<MenuTreeItemData>  $items
     */
    public function __construct(
        public MenuLocation $location,
        public string $locale,
        public ContentLocalesData $locales,
        public array $items,
        public MenuAbilitiesData $can,
    ) {}
}
