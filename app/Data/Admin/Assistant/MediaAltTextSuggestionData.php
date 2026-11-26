<?php

namespace App\Data\Admin\Assistant;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * JSON response of `admin.assistant.media-alt-text`: a proposal the admin
 * may copy into the alt text field.
 */
#[TypeScript]
class MediaAltTextSuggestionData extends Data
{
    public function __construct(
        public string $alt,
    ) {}
}
