<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden;

/**
 * Validated texts of the site settings for one public locale. Empty values
 * are null; a locale with all values null has no translation.
 */
#[Hidden]
class SiteSettingTranslationInputData extends Data
{
    public function __construct(
        public ?string $tagline,
        public ?string $footerText,
        public ?string $seoTitle,
        public ?string $seoDescription,
    ) {}

    public function isEmpty(): bool
    {
        return $this->tagline === null
            && $this->footerText === null
            && $this->seoTitle === null
            && $this->seoDescription === null;
    }
}
