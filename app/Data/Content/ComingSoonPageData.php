<?php

namespace App\Data\Content;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the public `public/coming-soon` page shown to guests while the
 * site is not launched yet.
 */
#[TypeScript]
class ComingSoonPageData extends Data
{
    public function __construct(
        public ?string $contactEmail,
    ) {}
}
