<?php

namespace App\Data\Settings;

use App\Enums\AccentColor;
use App\Enums\AnnouncementTone;
use App\Enums\ThemePreference;
use App\Enums\TypographyPreset;
use Carbon\CarbonImmutable;
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
     * @param  list<OpeningHoursDayData>|null  $openingHours  Seven days Monday first, or null when no hours are published.
     * @param  list<string>  $contactRecipients  Up to five distinct addresses; empty = the configured fallback.
     * @param  CarbonImmutable|null  $announcementStartsAt  First moment the bar is shown; null = right away.
     * @param  CarbonImmutable|null  $announcementEndsAt  Last moment the bar is shown; null = no end.
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
        public ?AccentColor $accent = null,
        public ?TypographyPreset $typography = null,
        public ThemePreference $defaultTheme = ThemePreference::System,
        public ?int $faviconMediaId = null,
        public ?string $taxId = null,
        public ?string $mapUrl = null,
        public ?array $openingHours = null,
        public ?string $googleSiteVerification = null,
        public ?string $plausibleDomain = null,
        public array $contactRecipients = [],
        public bool $contactAutoReply = false,
        public bool $announcementEnabled = false,
        public AnnouncementTone $announcementTone = AnnouncementTone::Info,
        public ?string $announcementLinkUrl = null,
        public ?CarbonImmutable $announcementStartsAt = null,
        public ?CarbonImmutable $announcementEndsAt = null,
    ) {}
}
