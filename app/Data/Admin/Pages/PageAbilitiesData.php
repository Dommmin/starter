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
    /**
     * @param  bool  $suggestSeo  The AI assistant may propose the meta description and slug.
     */
    public function __construct(
        public bool $create,
        public bool $update,
        public bool $publish,
        public bool $delete,
        public bool $suggestSeo = false,
    ) {}
}
