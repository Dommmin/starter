<?php

namespace App\Repositories\Settings;

use App\Data\Media\MediaImageData;
use App\Data\Settings\OpeningHoursDayData;
use App\Data\Settings\SiteAppearanceData;
use App\Data\Settings\SiteContactData;
use App\Data\Settings\SiteSettingsData;
use App\Data\Settings\SiteSocialLinkData;
use App\Enums\AccentColor;
use App\Enums\SocialNetwork;
use App\Enums\ThemePreference;
use App\Enums\TypographyPreset;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Services\Localization\LocalizationConfig;
use App\Services\Media\ImageVariantGenerator;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Read side of the site settings singleton.
 *
 * The row is cached forever under {@see self::CACHE_KEY} as a plain array
 * (no models): images are resolved to their public variant URLs when the
 * cache is built. UpdateSiteSettings and every change of a media asset
 * forget the key after commit.
 *
 * Fallbacks: without a row the site name and default og:image come from
 * `config/seo.php`, the contact form recipient from `config/contact.php`;
 * translated texts use the current locale, then the public fallback
 * locale, then the defaults in `lang/{locale}/public.php` (`site.*`) or the
 * site name.
 *
 * @phpstan-type TranslationRow array{tagline: string|null, footer_text: string|null, seo_title: string|null, seo_description: string|null, auto_reply_text: string|null}
 * @phpstan-type Snapshot array{
 *     exists: bool,
 *     site_name: string|null,
 *     logo: array<string, mixed>|null,
 *     og_image: string|null,
 *     contact_email: string|null,
 *     contact_phone: string|null,
 *     address_line: string|null,
 *     postal_code: string|null,
 *     city: string|null,
 *     country_code: string|null,
 *     recipient: string|null,
 *     recipients: list<string>,
 *     auto_reply: bool,
 *     social_links: array<string, string>,
 *     translations: array<string, TranslationRow>,
 *     accent: string|null,
 *     typography: string|null,
 *     default_theme: string,
 *     favicon: array{url: string, type: string}|null,
 *     tax_id: string|null,
 *     map_url: string|null,
 *     opening_hours: list<array{day: string, opens: string|null, closes: string|null}>|null,
 *     google_site_verification: string|null,
 *     coming_soon: bool,
 *     noindex: bool,
 * }
 */
class SiteSettingsRepository
{
    public const string CACHE_KEY = 'site_settings:v3';

    /**
     * Snapshot memoized for the current process (one cache read per request).
     *
     * @var Snapshot|null
     */
    private ?array $snapshot = null;

    public function __construct(private readonly LocalizationConfig $localization) {}

    /**
     * Public settings in the given locale (default: the application locale).
     */
    public function current(?string $locale = null): SiteSettingsData
    {
        $snapshot = $this->snapshot();
        $locale ??= app()->getLocale();

        $social = [];
        foreach (SocialNetwork::cases() as $network) {
            $url = $snapshot['social_links'][$network->value] ?? null;

            if (is_string($url) && $url !== '') {
                $social[] = new SiteSocialLinkData(network: $network, label: $network->label(), url: $url);
            }
        }

        return new SiteSettingsData(
            name: $this->siteName(),
            isCustomized: $snapshot['exists'],
            logo: $snapshot['logo'] === null ? null : MediaImageData::from($snapshot['logo']),
            tagline: $this->translated('tagline', $locale),
            footerText: $this->translated('footer_text', $locale),
            contact: new SiteContactData(
                email: $snapshot['contact_email'],
                phone: $snapshot['contact_phone'],
                address: $this->address($snapshot),
                taxId: $snapshot['tax_id'],
                mapUrl: $snapshot['map_url'],
                openingHours: OpeningHoursDayData::week($snapshot['opening_hours']),
            ),
            social: $social,
        );
    }

    /**
     * Brand name: the saved setting, then `seo.site_name`, then the
     * translated catalog brand shown by the text logo.
     */
    public function siteName(): string
    {
        $configured = config('seo.site_name');

        return $this->snapshot()['site_name']
            ?? (is_string($configured) && $configured !== '' ? $configured : __('common.brand.name'));
    }

    /**
     * Default page title in the given locale. Saved settings without an SEO
     * title use the site name; before the first save `site.defaultTitle`
     * from the public catalog applies.
     */
    public function defaultTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->translated('seo_title', $locale)
            ?? ($this->exists() ? null : $this->catalogDefault('defaultTitle', $locale))
            ?? $this->siteName();
    }

    /**
     * Default meta description in the given locale; before the first save
     * `site.defaultDescription` from the public catalog.
     */
    public function defaultDescription(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $this->translated('seo_description', $locale)
            ?? ($this->exists() ? null : $this->catalogDefault('defaultDescription', $locale));
    }

    /**
     * Absolute URL of the default og:image: the saved DAM image or
     * `seo.default_image`.
     */
    public function defaultImageUrl(): ?string
    {
        $image = $this->snapshot()['og_image'];

        if ($image !== null) {
            return $image;
        }

        $configured = config('seo.default_image');

        return is_string($configured) && $configured !== '' ? url($configured) : null;
    }

    /**
     * Appearance of the document: saved presets, otherwise `APP_ACCENT`,
     * the default typography and the system theme.
     */
    public function appearance(): SiteAppearanceData
    {
        $snapshot = $this->snapshot();

        return new SiteAppearanceData(
            accent: AccentColor::tryFrom((string) $snapshot['accent'])
                ?? AccentColor::tryFrom((string) config('app.accent'))
                ?? AccentColor::Default,
            typography: TypographyPreset::tryFrom((string) $snapshot['typography']) ?? TypographyPreset::Default,
            defaultTheme: ThemePreference::tryFrom($snapshot['default_theme']) ?? ThemePreference::System,
            faviconUrl: $snapshot['favicon']['url'] ?? null,
            faviconType: $snapshot['favicon']['type'] ?? null,
            googleSiteVerification: $snapshot['google_site_verification'],
        );
    }

    /**
     * Schema.org description of the business for the home page JSON-LD:
     * `LocalBusiness` when a street address and opening hours are saved,
     * `Organization` otherwise. Empty values are omitted.
     *
     * @return array<string, mixed>
     */
    public function organizationSchema(): array
    {
        $snapshot = $this->snapshot();
        $configuredUrl = config('seo.organization.url');
        $configuredLogo = config('seo.organization.logo');
        $week = OpeningHoursDayData::week($snapshot['opening_hours']) ?? [];
        $openDays = array_values(array_filter($week, fn (OpeningHoursDayData $day): bool => $day->isOpen()));
        $isLocal = $snapshot['address_line'] !== null && $snapshot['city'] !== null && $openDays !== [];

        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $snapshot['address_line'],
            'postalCode' => $snapshot['postal_code'],
            'addressLocality' => $snapshot['city'],
            'addressCountry' => $snapshot['country_code'],
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $isLocal ? 'LocalBusiness' : 'Organization',
            'name' => $this->siteName(),
            'url' => is_string($configuredUrl) && $configuredUrl !== '' ? url($configuredUrl) : url('/'),
            'logo' => $snapshot['logo']['src'] ?? (is_string($configuredLogo) && $configuredLogo !== '' ? url($configuredLogo) : null),
            'email' => $snapshot['contact_email'],
            'telephone' => $snapshot['contact_phone'],
            'address' => count($address) > 1 ? $address : null,
            'taxID' => $snapshot['tax_id'],
            'hasMap' => $snapshot['map_url'],
            'openingHoursSpecification' => $openDays === [] ? null : array_map(fn (OpeningHoursDayData $day): array => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $day->day->schemaName(),
                'opens' => $day->opens,
                'closes' => $day->closes,
            ], $openDays),
            'sameAs' => array_values($snapshot['social_links']) ?: null,
        ];

        return array_filter($schema, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Whether guests see the coming soon page instead of the public site.
     */
    public function isComingSoon(): bool
    {
        return $this->snapshot()['coming_soon'];
    }

    /**
     * Whether the whole site asks search engines not to index it (also
     * true while the coming soon page is on).
     */
    public function isNoindex(): bool
    {
        return $this->snapshot()['noindex'] || $this->snapshot()['coming_soon'];
    }

    /**
     * The site-wide noindex switch alone (without the coming soon page).
     */
    public function isNoindexSwitchOn(): bool
    {
        return $this->snapshot()['noindex'];
    }

    /**
     * Whether an administrator has saved the settings at least once.
     */
    public function exists(): bool
    {
        return $this->snapshot()['exists'];
    }

    /**
     * Address that receives contact form messages: the saved recipient or
     * `contact.recipient` (environment). Server-side only; never shared
     * with the browser.
     */
    public function contactRecipient(): string
    {
        return $this->contactRecipients()[0];
    }

    /**
     * Every address that receives contact form messages: the saved list,
     * then the single saved recipient, then `contact.recipient`.
     * Server-side only.
     *
     * @return non-empty-list<string>
     */
    public function contactRecipients(): array
    {
        $snapshot = $this->snapshot();

        if ($snapshot['recipients'] !== []) {
            return $snapshot['recipients'];
        }

        return [$snapshot['recipient'] ?? (string) config('contact.recipient')];
    }

    /**
     * Text of the automatic reply to the sender in the given locale (then
     * the public fallback locale); null when the auto-reply is off or has
     * no text.
     */
    public function autoReplyText(string $locale): ?string
    {
        return $this->snapshot()['auto_reply'] ? $this->translated('auto_reply_text', $locale) : null;
    }

    /**
     * The singleton row with every translation for the admin form (not
     * cached); null until the settings are saved for the first time.
     */
    public function forEditor(): ?SiteSetting
    {
        return SiteSetting::query()
            ->with('translations')
            ->find(SiteSetting::SINGLETON_ID);
    }

    /**
     * Drop the cached snapshot; the next read rebuilds it from the database.
     */
    public function forget(): void
    {
        $this->snapshot = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Snapshot
     */
    private function snapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        try {
            /** @var Snapshot $snapshot */
            $snapshot = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->load());
        } catch (Throwable $exception) {
            /*
             * Error pages share these settings too: an unavailable database
             * or cache must not break them. The failure is reported and the
             * configuration fallbacks are used for this request only.
             */
            report($exception);
            $snapshot = $this->missing();
        }

        return $this->snapshot = $snapshot;
    }

    /**
     * @return Snapshot
     */
    private function load(): array
    {
        $settings = SiteSetting::query()
            ->with(['translations', 'logo', 'ogImage', 'favicon'])
            ->find(SiteSetting::SINGLETON_ID);

        if ($settings === null) {
            return $this->missing();
        }

        $translations = [];
        foreach ($settings->translations as $translation) {
            $translations[$translation->locale] = [
                'tagline' => $translation->tagline,
                'footer_text' => $translation->footer_text,
                'seo_title' => $translation->seo_title,
                'seo_description' => $translation->seo_description,
                'auto_reply_text' => $translation->auto_reply_text,
            ];
        }

        return [
            'exists' => true,
            'site_name' => $settings->site_name,
            'logo' => $this->image($settings->logo)?->toArray(),
            'og_image' => $this->image($settings->ogImage)?->src,
            'contact_email' => $settings->contact_email,
            'contact_phone' => $settings->contact_phone,
            'address_line' => $settings->address_line,
            'postal_code' => $settings->postal_code,
            'city' => $settings->city,
            'country_code' => $settings->country_code,
            'recipient' => $settings->contact_recipient_email,
            'recipients' => $settings->contact_recipients ?? [],
            'auto_reply' => $settings->contact_auto_reply,
            'social_links' => $settings->social_links ?? [],
            'translations' => $translations,
            'accent' => $settings->accent?->value,
            'typography' => $settings->typography?->value,
            'default_theme' => $settings->default_theme->value,
            'favicon' => $this->favicon($settings->favicon),
            'tax_id' => $settings->tax_id,
            'map_url' => $settings->map_url,
            'opening_hours' => $settings->opening_hours,
            'google_site_verification' => $settings->google_site_verification,
            'coming_soon' => $settings->coming_soon,
            'noindex' => $settings->noindex,
        ];
    }

    /**
     * Snapshot of settings that were never saved (configuration fallbacks).
     *
     * @return Snapshot
     */
    private function missing(): array
    {
        return [
            'exists' => false,
            'site_name' => null,
            'logo' => null,
            'og_image' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'address_line' => null,
            'postal_code' => null,
            'city' => null,
            'country_code' => null,
            'recipient' => null,
            'recipients' => [],
            'auto_reply' => false,
            'social_links' => [],
            'translations' => [],
            'accent' => null,
            'typography' => null,
            'default_theme' => ThemePreference::System->value,
            'favicon' => null,
            'tax_id' => null,
            'map_url' => null,
            'opening_hours' => null,
            'google_site_verification' => null,
            'coming_soon' => false,
            'noindex' => false,
        ];
    }

    private function catalogDefault(string $key, string $locale): ?string
    {
        $value = __("public.site.{$key}", [], $locale);

        return is_string($value) && $value !== "public.site.{$key}" ? $value : null;
    }

    /**
     * Smallest PNG or JPEG variant of a clean image, used as the favicon.
     *
     * @return array{url: string, type: string}|null
     */
    private function favicon(?MediaAsset $asset): ?array
    {
        if ($asset === null || ! $asset->hasPassedScan() || ! $asset->isImage()) {
            return null;
        }

        $candidates = array_values(array_filter(
            $asset->variants ?? [],
            fn (array $variant): bool => in_array($variant['format'], ['png', 'jpeg'], true),
        ));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $a, array $b): int => $a['width'] <=> $b['width']);

        return [
            'url' => ImageVariantGenerator::publicDisk()->url($candidates[0]['path']),
            'type' => 'image/'.$candidates[0]['format'],
        ];
    }

    private function image(?MediaAsset $asset): ?MediaImageData
    {
        return $asset === null ? null : MediaImageData::fromAsset($asset);
    }

    /**
     * @param  'tagline'|'footer_text'|'seo_title'|'seo_description'|'auto_reply_text'  $field
     */
    private function translated(string $field, string $locale): ?string
    {
        $translations = $this->snapshot()['translations'];

        foreach ([$locale, $this->localization->getPublicFallback()] as $candidate) {
            $value = $translations[$candidate][$field] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  Snapshot  $snapshot
     */
    private function address(array $snapshot): ?string
    {
        $lines = array_filter([
            $snapshot['address_line'],
            trim(($snapshot['postal_code'] ?? '').' '.($snapshot['city'] ?? '')),
            $snapshot['country_code'],
        ], fn (?string $line): bool => $line !== null && $line !== '');

        return $lines === [] ? null : implode("\n", $lines);
    }
}
