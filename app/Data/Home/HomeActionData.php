<?php

namespace App\Data\Home;

use App\Enums\HomeLinkTarget;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stored button of a hero or CTA section: a label and a closed link target.
 * `pageId` is used only by the `page` target.
 */
#[TypeScript]
class HomeActionData extends Data
{
    public function __construct(
        public string $label,
        public HomeLinkTarget $target,
        public ?int $pageId = null,
    ) {}
}
