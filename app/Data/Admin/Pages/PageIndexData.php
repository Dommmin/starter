<?php

namespace App\Data\Admin\Pages;

use App\Data\Content\ContentLocalesData;
use App\Data\Listing\ListPaginationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/pages/index` screen: the uniform list payload
 * `{ items, pagination, filters }` plus content locales and abilities.
 */
#[TypeScript]
class PageIndexData extends Data
{
    /**
     * @param  list<PageListItemData>  $items
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
        public PageListFiltersData $filters,
        public ContentLocalesData $locales,
        public PageAbilitiesData $can,
    ) {}
}
