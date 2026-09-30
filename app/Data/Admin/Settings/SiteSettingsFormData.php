<?php

namespace App\Data\Admin\Settings;

use App\Enums\SocialNetwork;
use App\Models\SiteSetting;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state of the site settings screen. `updatedAt` is null until the
 * settings are saved for the first time and must be sent back on update
 * (optimistic locking). `socialLinks` lists every allowed network (empty
 * string = no link) and `translations` every active public locale.
 */
#[TypeScript]
class SiteSettingsFormData extends Data
{
    /**
     * @param  array<string, string>  $socialLinks
     * @param  array<string, SiteSettingTranslationFormData>  $translations
     */
    public function __construct(
        public ?string $updatedAt,
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
        #[LiteralTypeScriptType('{ [network in App.Enums.SocialNetwork]: string }')]
        public array $socialLinks,
        public array $translations,
    ) {}

    /**
     * Form for settings that were never saved: the fallback site name and
     * empty fields.
     *
     * @param  list<string>  $locales
     */
    public static function blank(string $siteName, array $locales): self
    {
        $translations = [];

        foreach ($locales as $locale) {
            $translations[$locale] = SiteSettingTranslationFormData::blank();
        }

        return new self(
            updatedAt: null,
            siteName: $siteName,
            logoMediaId: null,
            ogImageMediaId: null,
            contactEmail: null,
            contactPhone: null,
            addressLine: null,
            postalCode: null,
            city: null,
            countryCode: null,
            contactRecipientEmail: null,
            socialLinks: self::socialLinks([]),
            translations: $translations,
        );
    }

    /**
     * Requires the `translations` relation to be eager loaded.
     *
     * @param  list<string>  $locales
     */
    public static function fromSettings(SiteSetting $settings, array $locales): self
    {
        $byLocale = $settings->translations->keyBy('locale');
        $translations = [];

        foreach ($locales as $locale) {
            $translation = $byLocale->get($locale);

            $translations[$locale] = $translation === null
                ? SiteSettingTranslationFormData::blank()
                : SiteSettingTranslationFormData::fromTranslation($translation);
        }

        return new self(
            updatedAt: $settings->updated_at?->toIso8601String(),
            siteName: $settings->site_name,
            logoMediaId: $settings->logo_media_id,
            ogImageMediaId: $settings->og_image_media_id,
            contactEmail: $settings->contact_email,
            contactPhone: $settings->contact_phone,
            addressLine: $settings->address_line,
            postalCode: $settings->postal_code,
            city: $settings->city,
            countryCode: $settings->country_code,
            contactRecipientEmail: $settings->contact_recipient_email,
            socialLinks: self::socialLinks($settings->social_links ?? []),
            translations: $translations,
        );
    }

    /**
     * @param  array<string, string>  $stored
     * @return array<string, string>
     */
    private static function socialLinks(array $stored): array
    {
        $links = [];

        foreach (SocialNetwork::cases() as $network) {
            $links[$network->value] = $stored[$network->value] ?? '';
        }

        return $links;
    }
}
