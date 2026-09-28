<?php

namespace App\Http\Requests\Admin\Settings;

use App\Data\Settings\SiteSettingsInputData;
use App\Data\Settings\SiteSettingTranslationInputData;
use App\Enums\MediaStatus;
use App\Enums\SocialNetwork;
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
 */
class UpdateSiteSettingsRequest extends FormRequest
{
    /**
     * Control characters (including CR/LF) are never valid in single-line
     * settings: they would allow header injection in mails.
     */
    private const string CONTROL_CHARACTERS = '/\p{Cc}/u';

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
        ];

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
        );
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
}
