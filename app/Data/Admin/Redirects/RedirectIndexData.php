<?php

namespace App\Data\Admin\Redirects;

use App\Data\Listing\ListPaginationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/redirects/index` screen: the uniform list payload
 * `{ items, pagination, filters }` plus abilities.
 */
#[TypeScript]
class RedirectIndexData extends Data
{
    /**
     * @param  list<RedirectListItemData>  $items
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
        public RedirectListFiltersData $filters,
        public RedirectAbilitiesData $can,
    ) {}
}
