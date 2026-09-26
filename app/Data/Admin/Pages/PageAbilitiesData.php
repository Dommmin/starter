<?php

namespace App\Data\Admin\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints only; every operation is authorized again on the backend.
 */
#[TypeScript]
class PageAbilitiesData extends Data
{
    public function __construct(
        public bool $create,
        public bool $publish,
        public bool $delete,
    ) {}
}
