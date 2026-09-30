<?php

namespace App\Data\Admin\HomeSections;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A CMS page published in the section locale that an action may link to.
 */
#[TypeScript]
class HomePageOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
    ) {}
}
