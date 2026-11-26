<?php

namespace App\Data\Admin\Faqs;

use App\Data\Content\ContentLocalesData;
use App\Data\Listing\ListPaginationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/faqs/index` screen: the uniform list payload
 * `{ items, pagination, filters }` plus abilities and the content locales
 * (language of the FAQ entries proposed by the assistant).
 */
#[TypeScript]
class FaqIndexData extends Data
{
    /**
     * @param  list<FaqListItemData>  $items
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
        public FaqListFiltersData $filters,
        public FaqAbilitiesData $can,
        public ContentLocalesData $locales,
    ) {}
}
