<?php

namespace App\Actions\Settings;

use App\Actions\Audit\RecordAuditEvent;
use App\Data\Settings\SiteSettingsInputData;
use App\Data\Settings\SiteSettingTranslationInputData;
use App\Enums\AuditAction;
use App\Enums\SocialNetwork;
use App\Http\Requests\Admin\Settings\UpdateSiteSettingsRequest;
use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use App\Models\User;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationConfig;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Write side of the site settings singleton (id = SiteSetting::SINGLETON_ID).
 *
 * Both entry points lock the row inside a transaction (creating it on the
 * first save), record `site_settings.updated` with the changed public
 * values, and forget the settings cache after commit. The contact form
 * recipient is audited only as "changed", without its value.
 *
 * - handle(): complete update from the admin form with optimistic locking
 *   (`expectedUpdatedAt` = version the form was loaded with, null when the
 *   settings were never saved).
 * - updateSiteName(): partial update of the brand name only, without a
 *   version check (console / init-project; `$actor` = null).
 */
class UpdateSiteSettings
{
    /**
     * Setting attributes whose old and new values are safe to audit.
     */
    private const array AUDITED_FIELDS = [
        'site_name',
        'logo_media_id',
        'og_image_media_id',
        'contact_email',
        'contact_phone',
        'address_line',
        'postal_code',
        'city',
        'country_code',
        'social_links',
    ];

    private const array TRANSLATION_FIELDS = ['tagline', 'footer_text', 'seo_title', 'seo_description'];

    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly SiteSettingsRepository $settings,
        private readonly LocalizationConfig $localization,
    ) {}

    /**
     * Replace every setting and the translations of all active public
     * locales; locales missing from the input (or empty) lose their texts.
     *
     * @param  User|null  $actor  Null for console commands.
     *
     * @throws ValidationException When the settings changed in the meantime (`conflict`).
     */
    public function handle(SiteSettingsInputData $input, ?User $actor, ?string $expectedUpdatedAt): SiteSetting
    {
        return $this->persist($actor, function (SiteSetting $settings) use ($input, $expectedUpdatedAt): array {
            if (! $this->isVersion($settings, $expectedUpdatedAt)) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.siteSettings.conflict'),
                ]);
            }

            $settings->forceFill([
                'site_name' => $input->siteName,
                'logo_media_id' => $input->logoMediaId,
                'og_image_media_id' => $input->ogImageMediaId,
                'contact_email' => $input->contactEmail,
                'contact_phone' => $input->contactPhone,
                'address_line' => $input->addressLine,
                'postal_code' => $input->postalCode,
                'city' => $input->city,
                'country_code' => $input->countryCode,
                'contact_recipient_email' => $input->contactRecipientEmail,
                'social_links' => $this->orderedSocialLinks($input->socialLinks),
            ]);

            return [
                ...$this->settingChanges($settings),
                ...$this->syncTranslations($settings, $input->translations),
            ];
        });
    }

    /**
     * Change only the brand name (e.g. from the init-project command). The
     * name is validated with the same rules as the admin form.
     *
     * @param  User|null  $actor  Null for console commands.
     *
     * @throws ValidationException When the name is invalid (`site_name`).
     */
    public function updateSiteName(string $siteName, ?User $actor = null): SiteSetting
    {
        $siteName = trim($siteName);

        Validator::make(
            ['site_name' => $siteName],
            ['site_name' => UpdateSiteSettingsRequest::siteNameRules()],
        )->validate();

        return $this->persist($actor, function (SiteSetting $settings) use ($siteName): array {
            $settings->site_name = $siteName;

            return $this->settingChanges($settings);
        });
    }

    /**
     * @param  Closure(SiteSetting): array<string, array{old?: mixed, new?: mixed, redacted?: bool}>  $apply
     */
    private function persist(?User $actor, Closure $apply): SiteSetting
    {
        return DB::transaction(function () use ($actor, $apply): SiteSetting {
            $settings = $this->lockSingleton();
            $changes = $apply($settings);

            if ($changes !== []) {
                $this->audit->handle(AuditAction::SiteSettingsUpdated, $settings, $actor, $changes);
            }

            $now = $settings->freshTimestamp();
            $settings->forceFill([
                'updated_by' => $actor?->id,
                'created_at' => $settings->created_at ?? $now,
                'updated_at' => $now,
            ])->save();

            DB::afterCommit(fn () => $this->settings->forget());

            return $settings;
        });
    }

    /**
     * Lock the singleton row, creating it first when the settings were never
     * saved. `insertOrIgnore` keeps concurrent first saves safe: the loser
     * waits for the winner's row and then fails the version check.
     */
    private function lockSingleton(): SiteSetting
    {
        $settings = SiteSetting::query()->whereKey(SiteSetting::SINGLETON_ID)->lockForUpdate()->first();

        if ($settings !== null) {
            return $settings;
        }

        SiteSetting::query()->insertOrIgnore([
            'id' => SiteSetting::SINGLETON_ID,
            'site_name' => mb_substr($this->settings->siteName(), 0, 120),
        ]);

        return SiteSetting::query()->whereKey(SiteSetting::SINGLETON_ID)->lockForUpdate()->firstOrFail();
    }

    private function isVersion(SiteSetting $settings, ?string $expectedUpdatedAt): bool
    {
        $expected = $expectedUpdatedAt === null ? null : Carbon::parse($expectedUpdatedAt)->getTimestamp();

        return $settings->updated_at?->getTimestamp() === $expected;
    }

    /**
     * @return array<string, array{old?: mixed, new?: mixed, redacted?: bool}>
     */
    private function settingChanges(SiteSetting $settings): array
    {
        $changes = [];

        foreach (self::AUDITED_FIELDS as $field) {
            if ($settings->isDirty($field)) {
                $changes[$field] = RecordAuditEvent::change($settings->getOriginal($field), $settings->getAttribute($field));
            }
        }

        if ($settings->isDirty('contact_recipient_email')) {
            $changes['contact_recipient_email'] = RecordAuditEvent::redacted();
        }

        return $changes;
    }

    /**
     * @param  array<string, SiteSettingTranslationInputData>  $translations
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function syncTranslations(SiteSetting $settings, array $translations): array
    {
        $existing = $settings->translations()->get()->keyBy('locale');
        $changes = [];

        foreach ($this->localization->getPublicLocales() as $locale) {
            $input = $translations[$locale] ?? null;
            /** @var SiteSettingTranslation|null $translation */
            $translation = $existing->get($locale);

            if ($input === null || $input->isEmpty()) {
                if ($translation !== null) {
                    foreach (self::TRANSLATION_FIELDS as $field) {
                        if ($translation->getAttribute($field) !== null) {
                            $changes["{$locale}.{$field}"] = RecordAuditEvent::change($translation->getAttribute($field), null);
                        }
                    }

                    $translation->delete();
                }

                continue;
            }

            $translation ??= $settings->translations()->make(['locale' => $locale]);
            $translation->fill([
                'tagline' => $input->tagline,
                'footer_text' => $input->footerText,
                'seo_title' => $input->seoTitle,
                'seo_description' => $input->seoDescription,
            ]);

            foreach (self::TRANSLATION_FIELDS as $field) {
                $old = $translation->exists ? $translation->getOriginal($field) : null;
                $new = $translation->getAttribute($field);

                if ($old !== $new) {
                    $changes["{$locale}.{$field}"] = RecordAuditEvent::change($old, $new);
                }
            }

            $translation->save();
        }

        return $changes;
    }

    /**
     * Allowed networks in enum order; null when no link is set.
     *
     * @param  array<string, string>  $links
     * @return array<string, string>|null
     */
    private function orderedSocialLinks(array $links): ?array
    {
        $ordered = [];

        foreach (SocialNetwork::cases() as $network) {
            $url = $links[$network->value] ?? null;

            if (is_string($url) && $url !== '') {
                $ordered[$network->value] = $url;
            }
        }

        return $ordered === [] ? null : $ordered;
    }
}
