<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden;

/**
 * Complete, validated site settings passed to UpdateSiteSettings::handle().
 * Validation rules live only in UpdateSiteSettingsRequest; optional values
 * are null when empty.
 */
#[Hidden]
class SiteSettingsInputData extends Data
{
    /**
     * @param  array<string, string>  $socialLinks  SocialNetwork value => https URL; networks without a link are omitted.
     * @param  array<string, SiteSettingTranslationInputData>  $translations  Keyed by public locale; missing locales lose their translation.
     */
    public function __construct(
        public string $siteName,
        public ?int $logoMediaId,
        public ?int $ogImageMediaId,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $addressLine,
        public ?string $postalCode,
        public ?string $city,
        public ?string $countryCode,
        public ?string $contactRecipientEmail,
        public array $socialLinks,
        public array $translations,
    ) {}
}
