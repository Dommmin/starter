<?php

namespace App\Data\Admin\Media;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints only; every operation is authorized again on the backend.
 */
#[TypeScript]
class MediaAssetAbilitiesData extends Data
{
    /**
     * @param  bool  $suggestAlt  The AI assistant may propose the alt text of this clean image.
     */
    public function __construct(
        public bool $create,
        public bool $update,
        public bool $delete,
        public bool $suggestAlt = false,
    ) {}
}
