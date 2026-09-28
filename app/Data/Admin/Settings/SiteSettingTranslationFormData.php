<?php

namespace App\Data\Admin\Settings;

use App\Models\SiteSettingTranslation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Editable texts of the site settings in one public locale.
 */
#[TypeScript]
class SiteSettingTranslationFormData extends Data
{
    public function __construct(
        public ?string $tagline,
        public ?string $footerText,
        public ?string $seoTitle,
        public ?string $seoDescription,
    ) {}

    public static function blank(): self
    {
        return new self(tagline: null, footerText: null, seoTitle: null, seoDescription: null);
    }

    public static function fromTranslation(SiteSettingTranslation $translation): self
    {
        return new self(
            tagline: $translation->tagline,
            footerText: $translation->footer_text,
            seoTitle: $translation->seo_title,
            seoDescription: $translation->seo_description,
        );
    }
}
