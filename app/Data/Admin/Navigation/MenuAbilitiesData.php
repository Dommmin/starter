<?php

namespace App\Data\Admin\Navigation;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints only; every operation is authorized again on the backend.
 */
#[TypeScript]
class MenuAbilitiesData extends Data
{
    public function __construct(
        public bool $create,
        public bool $reorder,
        public bool $delete,
    ) {}
}
