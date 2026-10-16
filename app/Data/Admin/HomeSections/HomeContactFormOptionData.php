<?php

namespace App\Data\Admin\HomeSections;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A contact form the `contact` section may show.
 */
#[TypeScript]
class HomeContactFormOptionData extends Data
{
    public function __construct(
        public string $key,
        public string $title,
    ) {}
}
