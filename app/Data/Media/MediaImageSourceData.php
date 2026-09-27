<?php

namespace App\Data\Media;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One `<source>` of a responsive picture: MIME type and width-descriptor srcset.
 */
#[TypeScript]
class MediaImageSourceData extends Data
{
    public function __construct(
        public string $type,
        public string $srcset,
    ) {}
}
