<?php

namespace App\Data\Admin\Settings;

use App\Data\Settings\OpeningHoursDayData;
use App\Enums\AccentColor;
use App\Enums\SocialNetwork;
use App\Enums\ThemePreference;
use App\Enums\TypographyPreset;
use App\Enums\Weekday;
use App\Models\SiteSetting;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state of the site settings screen. `updatedAt` is null until the
 * settings are saved for the first time and must be sent back on update
 * (optimistic locking). `socialLinks` lists every allowed network (empty
 * string = no link) and `translations` every active public locale.
 * `openingHours` lists every Weekday with `HH:MM` times (empty strings =
 * closed); `accent` and `typography` are null while the defaults apply.
 */
#[TypeScript]
class SiteSettingsFormData extends Data
{
    /**
     * @param  array<string, string>  $socialLinks
     * @param  array<string, SiteSettingTranslationFormData>  $translations
     * @param  array<string, array{opens: string, closes: string}>  $openingHours
     * @param  list<string>  $contactRecipients
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
        public ?AccentColor $accent,
        public ?TypographyPreset $typography,
        public ThemePreference $defaultTheme,
        public ?int $faviconMediaId,
        public ?string $taxId,
        public ?string $mapUrl,
        #[LiteralTypeScriptType('{ [day in App.Enums.Weekday]: { opens: string; closes: string } }')]
        public array $openingHours,
        public ?string $googleSiteVerification,
        public array $contactRecipients,
        public bool $contactAutoReply,
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
            accent: null,
            typography: null,
            defaultTheme: ThemePreference::System,
            faviconMediaId: null,
            taxId: null,
            mapUrl: null,
            openingHours: self::openingHours(null),
            googleSiteVerification: null,
            contactRecipients: [],
            contactAutoReply: false,
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
            accent: $settings->accent,
            typography: $settings->typography,
            defaultTheme: $settings->default_theme,
            faviconMediaId: $settings->favicon_media_id,
            taxId: $settings->tax_id,
            mapUrl: $settings->map_url,
            openingHours: self::openingHours(OpeningHoursDayData::week($settings->opening_hours)),
            googleSiteVerification: $settings->google_site_verification,
            contactRecipients: $settings->contact_recipients
                ?? ($settings->contact_recipient_email === null ? [] : [$settings->contact_recipient_email]),
            contactAutoReply: $settings->contact_auto_reply,
        );
    }

    /**
     * @param  list<OpeningHoursDayData>|null  $week
     * @return array<string, array{opens: string, closes: string}>
     */
    private static function openingHours(?array $week): array
    {
        $hours = [];

        foreach (Weekday::cases() as $index => $day) {
            $stored = $week === null ? null : $week[$index];
            $hours[$day->value] = ['opens' => $stored === null ? '' : ($stored->opens ?? ''), 'closes' => $stored === null ? '' : ($stored->closes ?? '')];
        }

        return $hours;
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
