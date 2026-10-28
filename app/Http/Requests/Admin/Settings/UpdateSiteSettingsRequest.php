<?php

namespace App\Http\Requests\Admin\Settings;

use App\Data\Settings\OpeningHoursDayData;
use App\Data\Settings\SiteSettingsInputData;
use App\Data\Settings\SiteSettingTranslationInputData;
use App\Enums\AccentColor;
use App\Enums\MediaStatus;
use App\Enums\SocialNetwork;
use App\Enums\ThemePreference;
use App\Enums\TypographyPreset;
use App\Enums\Weekday;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Complete site settings from the admin form. Empty strings arrive as null
 * (ConvertEmptyStringsToNull). `translations` accepts only active public
 * locales; `social_links` only SocialNetwork keys with https URLs.
 * `opening_hours` is keyed by Weekday; a day without both times is closed.
 */
class UpdateSiteSettingsRequest extends FormRequest
{
    /**
     * Control characters (including CR/LF) are never valid in single-line
     * settings: they would allow header injection in mails.
     */
    private const string CONTROL_CHARACTERS = '/\p{Cc}/u';

    /**
     * Bare host name of the Plausible site (e.g. `example.com`): at least
     * two dot-separated labels of letters, digits and inner hyphens, without
     * a scheme, path, port or spaces.
     */
    private const string HOSTNAME = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/i';

    /** Contact form recipients the settings accept. */
    public const int MAX_RECIPIENTS = 5;

    /**
     * The route checks `update`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', SiteSetting::class) ?? false;
    }

    /**
     * Rules of the brand name, shared with UpdateSiteSettings::updateSiteName().
     *
     * @return list<string>
     */
    public static function siteNameRules(): array
    {
        return ['required', 'string', 'max:120', 'not_regex:'.self::CONTROL_CHARACTERS];
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic
     * locking); null when the settings were never saved.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $singleLine = 'not_regex:'.self::CONTROL_CHARACTERS;
        $rules = [
            'updated_at' => ['nullable', 'string', 'date'],
            'site_name' => self::siteNameRules(),
            'logo_media_id' => ['nullable', 'integer', $this->cleanImage()],
            'og_image_media_id' => ['nullable', 'integer', $this->cleanImage()],
            'contact_email' => ['nullable', 'string', 'max:254', $singleLine, 'email:rfc,strict'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9 ()\/.\-]+$/'],
            'address_line' => ['nullable', 'string', 'max:200', $singleLine],
            'postal_code' => ['nullable', 'string', 'max:20', $singleLine],
            'city' => ['nullable', 'string', 'max:100', $singleLine],
            'country_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'contact_recipient_email' => ['nullable', 'string', 'max:254', $singleLine, 'email:rfc,strict'],
            'social_links' => ['nullable', 'array:'.implode(',', SocialNetwork::values())],
            'translations' => ['required', 'array:'.implode(',', $this->locales())],
            'accent' => ['nullable', Rule::enum(AccentColor::class)],
            'typography' => ['nullable', Rule::enum(TypographyPreset::class)],
            'default_theme' => ['required', Rule::enum(ThemePreference::class)],
            'favicon_media_id' => ['nullable', 'integer', $this->cleanImage()],
            'tax_id' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'map_url' => ['nullable', 'string', 'max:500', $singleLine, 'starts_with:https://', 'url:https'],
            'google_site_verification' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'plausible_domain' => ['nullable', 'string', 'max:253', 'regex:'.self::HOSTNAME],
            'opening_hours' => ['nullable', 'array:'.implode(',', array_column(Weekday::cases(), 'value'))],
            'contact_recipients' => ['nullable', 'array', 'list', 'max:'.self::MAX_RECIPIENTS],
            'contact_recipients.*' => ['required', 'string', 'max:254', $singleLine, 'email:rfc,strict', 'distinct:ignore_case'],
            'contact_auto_reply' => ['required', 'boolean'],
        ];

        foreach (Weekday::cases() as $day) {
            $prefix = "opening_hours.{$day->value}";
            $rules[$prefix] = ['nullable', 'array:opens,closes'];
            $rules["{$prefix}.opens"] = ['nullable', 'date_format:H:i', "required_with:{$prefix}.closes"];
            $rules["{$prefix}.closes"] = ['nullable', 'date_format:H:i', "required_with:{$prefix}.opens", "after:{$prefix}.opens"];
        }

        foreach (SocialNetwork::values() as $network) {
            $rules["social_links.{$network}"] = ['nullable', 'string', 'max:2048', $singleLine, 'starts_with:https://', 'url:https'];
        }

        foreach ($this->locales() as $locale) {
            $prefix = "translations.{$locale}";
            $rules[$prefix] = ['nullable', 'array'];
            $rules["{$prefix}.tagline"] = ['nullable', 'string', 'max:200', $singleLine];
            $rules["{$prefix}.footer_text"] = ['nullable', 'string', 'max:500'];
            $rules["{$prefix}.seo_title"] = ['nullable', 'string', 'max:70', $singleLine];
            $rules["{$prefix}.seo_description"] = ['nullable', 'string', 'max:320'];
            $rules["{$prefix}.auto_reply_text"] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }

    public function expectedUpdatedAt(): ?string
    {
        $updatedAt = $this->validated('updated_at');

        return is_string($updatedAt) ? $updatedAt : null;
    }

    /**
     * Map the validated payload to the action input.
     */
    public function toInput(): SiteSettingsInputData
    {
        $validated = $this->validated();

        /** @var array<string, string|null> $social */
        $social = $validated['social_links'] ?? [];
        /** @var array<string, array<string, string|null>|null> $translations */
        $translations = $validated['translations'] ?? [];

        $links = [];
        foreach ($social as $network => $url) {
            if (is_string($url) && $url !== '') {
                $links[$network] = $url;
            }
        }

        $input = [];
        foreach ($this->locales() as $locale) {
            $translation = $translations[$locale] ?? null;

            if (! is_array($translation)) {
                continue;
            }

            $input[$locale] = new SiteSettingTranslationInputData(
                tagline: self::text($translation['tagline'] ?? null),
                footerText: self::text($translation['footer_text'] ?? null),
                seoTitle: self::text($translation['seo_title'] ?? null),
                seoDescription: self::text($translation['seo_description'] ?? null),
                autoReplyText: self::text($translation['auto_reply_text'] ?? null),
            );
        }

        return new SiteSettingsInputData(
            siteName: trim((string) $validated['site_name']),
            logoMediaId: isset($validated['logo_media_id']) ? (int) $validated['logo_media_id'] : null,
            ogImageMediaId: isset($validated['og_image_media_id']) ? (int) $validated['og_image_media_id'] : null,
            contactEmail: self::text($validated['contact_email'] ?? null),
            contactPhone: self::text($validated['contact_phone'] ?? null),
            addressLine: self::text($validated['address_line'] ?? null),
            postalCode: self::text($validated['postal_code'] ?? null),
            city: self::text($validated['city'] ?? null),
            countryCode: self::text($validated['country_code'] ?? null),
            contactRecipientEmail: self::text($validated['contact_recipient_email'] ?? null),
            socialLinks: $links,
            translations: $input,
            accent: isset($validated['accent']) ? AccentColor::from((string) $validated['accent']) : null,
            typography: isset($validated['typography']) ? TypographyPreset::from((string) $validated['typography']) : null,
            defaultTheme: ThemePreference::from((string) $validated['default_theme']),
            faviconMediaId: isset($validated['favicon_media_id']) ? (int) $validated['favicon_media_id'] : null,
            taxId: self::text($validated['tax_id'] ?? null),
            mapUrl: self::text($validated['map_url'] ?? null),
            openingHours: self::openingHours($validated['opening_hours'] ?? null),
            googleSiteVerification: self::text($validated['google_site_verification'] ?? null),
            plausibleDomain: self::hostname($validated['plausible_domain'] ?? null),
            contactRecipients: array_values(array_map(
                fn (mixed $email): string => trim((string) $email),
                (array) ($validated['contact_recipients'] ?? []),
            )),
            contactAutoReply: (bool) $validated['contact_auto_reply'],
        );
    }

    /**
     * Seven days Monday first, or null when no day is open.
     *
     * @return list<OpeningHoursDayData>|null
     */
    private static function openingHours(mixed $hours): ?array
    {
        if (! is_array($hours)) {
            return null;
        }

        $rows = [];
        foreach (Weekday::cases() as $day) {
            $times = is_array($hours[$day->value] ?? null) ? $hours[$day->value] : [];
            $rows[] = [
                'day' => $day->value,
                'opens' => self::text($times['opens'] ?? null),
                'closes' => self::text($times['closes'] ?? null),
            ];
        }

        $week = OpeningHoursDayData::week($rows);

        return $week !== null && array_filter($week, fn (OpeningHoursDayData $day): bool => $day->isOpen()) !== []
            ? $week
            : null;
    }

    /**
     * A clean DAM image with generated variants (as for article covers).
     */
    private function cleanImage(): Exists
    {
        return Rule::exists('media_assets', 'id')->where(function (Builder $query): void {
            $query->where('status', MediaStatus::Clean->value)
                ->whereIn('mime', MediaAsset::IMAGE_MIMES)
                ->whereNotNull('variants');
        });
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return app(LocalizationConfig::class)->getPublicLocales();
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Host names are case-insensitive; Plausible matches the lowercase form.
     */
    private static function hostname(mixed $value): ?string
    {
        $value = self::text($value);

        return $value === null ? null : strtolower($value);
    }
}
