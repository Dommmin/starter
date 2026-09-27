<?php

namespace App\Data\Admin\Media;

use App\Data\Listing\ListPaginationData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * JSON payload of `admin.media.picker`.
 */
#[TypeScript]
class MediaPickerData extends Data
{
    /**
     * @param  list<MediaPickerItemData>  $items
     */
    public function __construct(
        public array $items,
        public ListPaginationData $pagination,
    ) {}
}
