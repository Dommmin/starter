<?php

namespace App\Data\Admin\Contact;

use App\Data\Listing\ListPaginationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/contact/index` screen.
 */
#[TypeScript]
class ContactMessageIndexData extends Data
{
    /**
     * @param  list<ContactMessageListItemData>  $items
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
        public ContactMessageListFiltersData $filters,
    ) {}
}
