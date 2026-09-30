<?php

namespace Database\Seeders;

use App\Actions\Settings\UpdateSiteSettings;
use App\Data\Settings\SiteSettingsInputData;
use App\Data\Settings\SiteSettingTranslationInputData;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Database\Seeder;

/**
 * Local sample site settings: contact details, social links, logo and the
 * translated tagline, footer and SEO texts, stored through the audited
 * settings action. A site name already saved is kept; otherwise the demo
 * studio name is used instead of the technical APP_NAME. Idempotent: skipped
 * once a contact e-mail is set (by this seeder or by an editor).
 */
class SiteSettingsSeeder extends Seeder
{
    public const string SITE_NAME = 'Pracownia Nowak';

    /**
     * @var array<string, array{tagline: string, footer: string, seoTitle: string, seoDescription: string}>
     */
    public const array TRANSLATIONS = [
        'pl' => ['tagline' => 'Małe strony, duże możliwości', 'footer' => 'Pracownia z Łodzi. Wszystkie prawa zastrzeżone.', 'seoTitle' => 'Strony internetowe dla małych firm', 'seoDescription' => 'Projektujemy i utrzymujemy szybkie, dostępne strony internetowe dla małych firm i organizacji.'],
        'en' => ['tagline' => 'Small websites, big possibilities', 'footer' => 'A studio from Łódź. All rights reserved.', 'seoTitle' => 'Websites for small businesses', 'seoDescription' => 'We design and maintain fast, accessible websites for small businesses and organisations.'],
        'de' => ['tagline' => 'Kleine Websites, große Möglichkeiten', 'footer' => 'Ein Studio aus Łódź. Alle Rechte vorbehalten.', 'seoTitle' => 'Websites für kleine Unternehmen', 'seoDescription' => 'Wir gestalten und betreuen schnelle, barrierefreie Websites für kleine Unternehmen.'],
    ];

    public function run(UpdateSiteSettings $updateSiteSettings, LocalizationConfig $localization): void
    {
        $current = SiteSetting::query()->find(SiteSetting::SINGLETON_ID);

        if ($current?->contact_email !== null) {
            return;
        }

        $translations = [];
        foreach ($localization->getPublicLocales() as $locale) {
            $copy = self::TRANSLATIONS[$locale] ?? self::TRANSLATIONS['en'];
            $translations[$locale] = new SiteSettingTranslationInputData(
                tagline: $copy['tagline'],
                footerText: $copy['footer'],
                seoTitle: $copy['seoTitle'],
                seoDescription: $copy['seoDescription'],
            );
        }

        $updateSiteSettings->handle(
            new SiteSettingsInputData(
                siteName: $current->site_name ?? self::SITE_NAME,
                logoMediaId: DemoMediaSeeder::assetId('square'),
                ogImageMediaId: DemoMediaSeeder::assetId('landscape'),
                contactEmail: 'kontakt@example.com',
                contactPhone: '+48 42 123 45 67',
                addressLine: 'ul. Piotrkowska 104/7',
                postalCode: '90-926',
                city: 'Łódź',
                countryCode: 'PL',
                contactRecipientEmail: 'skrzynka@example.com',
                socialLinks: [
                    'facebook' => 'https://www.facebook.com/example',
                    'instagram' => 'https://www.instagram.com/example',
                    'linkedin' => 'https://www.linkedin.com/company/example',
                    'github' => 'https://github.com/example',
                ],
                translations: $translations,
            ),
            User::query()->where('email', DemoContent::USER_EMAIL)->first(),
            $current?->updated_at?->toIso8601String(),
        );
    }
}
