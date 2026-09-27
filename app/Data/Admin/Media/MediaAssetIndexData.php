<?php

namespace App\Data\Admin\Media;

use App\Data\Listing\ListPaginationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/media/index` screen: the uniform list payload
 * `{ items, pagination, filters }`, abilities and upload hints.
 */
#[TypeScript]
class MediaAssetIndexData extends Data
{
    /**
     * @param  list<MediaAssetListItemData>  $items
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
        public MediaAssetListFiltersData $filters,
        public MediaAssetAbilitiesData $can,
        public MediaUploadRulesData $upload,
    ) {}
}
