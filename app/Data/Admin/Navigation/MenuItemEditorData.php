<?php

namespace App\Data\Admin\Navigation;

use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/navigation/create` and `admin/navigation/edit`
 * screens: the item, and the pages, articles and parents selectable in its
 * menu locale.
 */
#[TypeScript]
class MenuItemEditorData extends Data
{
    /**
     * @param  list<MenuTargetOptionData>  $pages
     * @param  list<MenuTargetOptionData>  $articles
     * @param  list<MenuParentOptionData>  $parents
     */
    public function __construct(
        public MenuItemFormData $item,
        public ContentLocalesData $locales,
        public array $pages,
        public array $articles,
        public array $parents,
        public MenuAbilitiesData $can,
    ) {}
}
