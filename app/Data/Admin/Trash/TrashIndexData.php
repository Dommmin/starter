<?php

namespace App\Data\Admin\Trash;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/trash/index` screen.
 */
#[TypeScript]
class TrashIndexData extends Data
{
    /**
     * @param  list<TrashItemData>  $items  Pages first, then articles; newest first.
     */
    public function __construct(
        public array $items,
        public int $retentionDays,
    ) {}
}
