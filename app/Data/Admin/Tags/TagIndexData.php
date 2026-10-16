<?php

namespace App\Data\Admin\Tags;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/tags/index` screen.
 */
#[TypeScript]
class TagIndexData extends Data
{
    /**
     * @param  list<TagListItemData>  $items
     */
    public function __construct(
        public array $items,
        public bool $canCreate,
    ) {}
}
