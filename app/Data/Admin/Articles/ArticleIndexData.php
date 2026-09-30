<?php

namespace App\Data\Admin\Articles;

use App\Data\Content\ContentLocalesData;
use App\Data\Listing\ListPaginationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/articles/index` screen: the uniform list payload
 * `{ items, pagination, filters }` plus content locales and abilities.
 */
#[TypeScript]
class ArticleIndexData extends Data
{
    /**
     * @param  list<ArticleListItemData>  $items
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
        public ArticleListFiltersData $filters,
        public ContentLocalesData $locales,
        public ArticleAbilitiesData $can,
    ) {}
}
