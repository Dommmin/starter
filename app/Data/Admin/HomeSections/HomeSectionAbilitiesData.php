<?php

namespace App\Data\Admin\HomeSections;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints only; every operation is authorized again on the backend.
 */
#[TypeScript]
class HomeSectionAbilitiesData extends Data
{
    public function __construct(
        public bool $update,
        public bool $reorder,
    ) {}
}
