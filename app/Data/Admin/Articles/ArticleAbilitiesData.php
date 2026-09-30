<?php

namespace App\Data\Admin\Articles;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints only; every operation is authorized again on the backend.
 */
#[TypeScript]
class ArticleAbilitiesData extends Data
{
    public function __construct(
        public bool $create,
        public bool $publish,
        public bool $delete,
    ) {}
}
