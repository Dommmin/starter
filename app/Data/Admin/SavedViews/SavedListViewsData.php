<?php

namespace App\Data\Admin\SavedViews;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * JSON of `admin.saved-views.*`: the user's views of one list, by name.
 */
#[TypeScript]
class SavedListViewsData extends Data
{
    /**
     * @param  list<SavedListViewData>  $views
     */
    public function __construct(
        public array $views,
        public int $max,
    ) {}
}
