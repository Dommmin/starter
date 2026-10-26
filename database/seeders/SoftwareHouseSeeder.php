<?php

namespace Database\Seeders;

use App\Actions\Home\EnsureHomeSections;
use App\Actions\Settings\UpdateSiteSettings;
use App\Data\Settings\SiteSettingsInputData;
use App\Data\Settings\SiteSettingTranslationInputData;
use App\Enums\HomeSectionAnchor;
use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\AuditLog;
use App\Models\HomeSection;
use App\Models\MenuItem;
use App\Models\PageTranslation;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Localization\LocalizationConfig;
use App\Services\Navigation\PublicNavigation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\Data;

/**
 * Turn the local demo into the software house showcase: placeholder images,
 * blog articles ({@see SoftwareHouseArticleSeeder}), FAQs
 * ({@see SoftwareHouseFaqSeeder}), the "what you get" page
 * ({@see SoftwareHouseOfferPageSeeder}), home sections in the showcase order ({@see SoftwareHouseContent}, Polish,
 * English and German copy) and a header menu of section anchors in every
 * public locale.
 *
 * Run after the default seeders: `make artisan ARGS='db:seed --class=SoftwareHouseSeeder'`,
 * or on a showcase server through {@see SoftwareHouseShowcaseSeeder}. Only
 * where sample content is enabled, because the placeholder images skip the
 * malware scan like {@see DemoMediaSeeder} (they are drawn in process,
 * never uploaded). Idempotent and safe for edits: a locale is
 * rewritten only while none of its sections was changed by a person (every
 * panel edit, toggle and reorder is audited; seeders never are) or each
 * still holds seeded content, its header and footer menus only while no
 * item has an author, and the site settings only while the contact e-mail
 * is the demo or showcase one.
 */
class SoftwareHouseSeeder extends Seeder
{
    /** Fictional showcase contact address (reserved example domain). */
    public const string CONTACT_EMAIL = 'hello@punkt.example';

    public function run(LocalizationConfig $config, EnsureHomeSections $ensure, PublicNavigation $navigation, UpdateSiteSettings $settings): void
    {
        if (! DemoContent::samplesEnabled()) {
            throw new RuntimeException('SoftwareHouseSeeder seeds placeholder media without a malware scan and runs only where sample content is enabled (local, demo or SoftwareHouseShowcaseSeeder).');
        }

        $this->call([SoftwareHouseMediaSeeder::class, SoftwareHouseArticleSeeder::class, SoftwareHouseFaqSeeder::class, SoftwareHouseOfferPageSeeder::class]);
        $ensure->handle();

        $imageId = fn (string $key): ?int => SoftwareHouseMediaSeeder::assetId($key);

        foreach ($config->getPublicLocales() as $locale) {
            $this->seedSections($locale, SoftwareHouseContent::sections($locale, $imageId));
            $this->seedHeaderMenu($locale);
            $this->seedFooterMenu($locale);
        }

        $this->seedSiteSettings($config, $settings);

        $navigation->forget();
    }

    /**
     * Header menu of one locale: anchors of the showcase sections and the
     * article list.
     *
     * @return list<array{type: MenuItemType, label: string, anchor?: string}>
     */
    public static function headerMenu(string $locale): array
    {
        $anchor = fn (HomeSectionAnchor $anchor, string $polish, string $english, string $german): array => [
            'type' => MenuItemType::Anchor,
            'anchor' => $anchor->value,
            'label' => SoftwareHouseContent::pick($locale, $polish, $english, $german),
        ];

        return [
            $anchor(HomeSectionAnchor::Features, 'Usługi', 'Services', 'Leistungen'),
            $anchor(HomeSectionAnchor::Portfolio, 'Portfolio', 'Portfolio', 'Portfolio'),
            $anchor(HomeSectionAnchor::Team, 'Zespół', 'Team', 'Team'),
            $anchor(HomeSectionAnchor::Pricing, 'Cennik', 'Pricing', 'Preise'),
            ['type' => MenuItemType::ArticleIndex, 'label' => 'Blog'],
            $anchor(HomeSectionAnchor::Contact, 'Kontakt', 'Contact', 'Kontakt'),
        ];
    }

    /**
     * Footer menu of one locale: link groups of the showcase (offer, which
     * ends with the "what you get" page, and company, which ends with the
     * privacy policy; each page only when it exists in the locale).
     *
     * @return list<array{label: string, children: list<array{type: MenuItemType, label?: string, anchor?: string, page_id?: int}>}>
     */
    public static function footerMenu(string $locale): array
    {
        $t = fn (string $polish, string $english, string $german): string => SoftwareHouseContent::pick($locale, $polish, $english, $german);
        $anchor = fn (HomeSectionAnchor $anchor, string $label): array => ['type' => MenuItemType::Anchor, 'anchor' => $anchor->value, 'label' => $label];
        $privacyPageId = NavigationMenuSeeder::privacyPolicyPageId();
        $page = fn (?int $pageId): array => $pageId !== null && PageTranslation::query()->where('page_id', $pageId)->where('locale', $locale)->exists()
            ? [['type' => MenuItemType::Page, 'page_id' => $pageId]]
            : [];
        $legal = $page($privacyPageId);

        return [
            ['label' => $t('Oferta', 'Offer', 'Angebot'), 'children' => [
                $anchor(HomeSectionAnchor::Features, $t('Usługi', 'Services', 'Leistungen')),
                $anchor(HomeSectionAnchor::Portfolio, 'Portfolio'),
                $anchor(HomeSectionAnchor::Pricing, $t('Cennik', 'Pricing', 'Preise')),
                ...$page(SoftwareHouseOfferPageSeeder::pageId()),
            ]],
            ['label' => $t('Firma', 'Company', 'Unternehmen'), 'children' => [
                $anchor(HomeSectionAnchor::Team, $t('Zespół', 'Team', 'Team')),
                $anchor(HomeSectionAnchor::Testimonials, $t('Opinie', 'Testimonials', 'Referenzen')),
                $anchor(HomeSectionAnchor::Faq, 'FAQ'),
                ['type' => MenuItemType::ArticleIndex, 'label' => 'Blog'],
                $anchor(HomeSectionAnchor::Contact, $t('Kontakt', 'Contact', 'Kontakt')),
                ...$legal,
            ]],
        ];
    }

    /**
     * @param  array<string, array{enabled: bool, content: Data}>  $definitions
     */
    private function seedSections(string $locale, array $definitions): void
    {
        $sections = HomeSection::query()->where('locale', $locale)->get()->keyBy(
            fn (HomeSection $section): string => $section->type->value,
        );

        $changedByPerson = AuditLog::query()
            ->where('subject_type', (new HomeSection)->getMorphClass())
            ->whereIn('subject_id', $sections->modelKeys())
            ->pluck('subject_id')
            ->all();

        foreach ($sections as $type => $section) {
            if (in_array($section->id, $changedByPerson, true) && ! $this->holdsSeededContent($section, $definitions[$type]['content']->toArray())) {
                return;
            }
        }

        DB::transaction(function () use ($sections, $definitions): void {
            $position = 0;
            foreach ($definitions as $type => $definition) {
                $section = $sections->get($type);

                if ($section === null) {
                    continue;
                }

                $section->forceFill([
                    'position' => ++$position,
                    'enabled' => $definition['enabled'],
                    'content' => $definition['content'],
                ])->save();
            }
        });
    }

    /**
     * Whether a section still holds content a seeder wrote: the demo, the
     * neutral placeholder or this showcase.
     *
     * @param  array<string, mixed>  $showcase
     */
    private function holdsSeededContent(HomeSection $section, array $showcase): bool
    {
        $content = $section->content->toArray();

        return $content === $showcase
            || HomeSectionSeeder::isDemo($section)
            || $content === EnsureHomeSections::placeholder($section->type, $section->locale)->toArray();
    }

    private function seedHeaderMenu(string $locale): void
    {
        $items = MenuItem::query()
            ->where('location', MenuLocation::Header->value)
            ->where('locale', $locale);

        if ((clone $items)->where(fn ($query) => $query->whereNotNull('created_by')->orWhereNotNull('updated_by'))->exists()) {
            return;
        }

        $current = (clone $items)->whereNull('parent_id')->orderBy('position')->get()
            ->map(fn (MenuItem $item): array => [$item->type, $item->anchor, $item->label])
            ->all();
        $showcase = array_map(fn (array $item): array => [$item['type'], $item['anchor'] ?? null, $item['label']], self::headerMenu($locale));

        if ($current === $showcase && ! (clone $items)->whereNotNull('parent_id')->exists()) {
            return;
        }

        DB::transaction(function () use ($items, $locale): void {
            (clone $items)->whereNotNull('parent_id')->delete();
            (clone $items)->delete();

            foreach (self::headerMenu($locale) as $index => $attributes) {
                MenuItem::query()->create([
                    ...$attributes,
                    'location' => MenuLocation::Header,
                    'locale' => $locale,
                    'position' => $index + 1,
                ]);
            }
        });
    }

    /**
     * Footer menu of a locale, rewritten only while no item has an author
     * and it differs from the showcase.
     */
    private function seedFooterMenu(string $locale): void
    {
        $items = MenuItem::query()
            ->where('location', MenuLocation::Footer->value)
            ->where('locale', $locale);

        if ((clone $items)->where(fn ($query) => $query->whereNotNull('created_by')->orWhereNotNull('updated_by'))->exists()) {
            return;
        }

        $groups = self::footerMenu($locale);
        $current = (clone $items)->whereNull('parent_id')->orderBy('position')->with(['children' => fn ($query) => $query->orderBy('position')])->get()
            ->map(fn (MenuItem $item): array => [$item->label, $item->children->map(fn (MenuItem $child): array => [$child->type, $child->anchor, $child->page_id])->all()])
            ->all();
        $showcase = array_map(fn (array $group): array => [$group['label'], array_map(fn (array $child): array => [$child['type'], $child['anchor'] ?? null, $child['page_id'] ?? null], $group['children'])], $groups);

        if ($current === $showcase) {
            return;
        }

        DB::transaction(function () use ($items, $locale, $groups): void {
            (clone $items)->whereNotNull('parent_id')->delete();
            (clone $items)->delete();

            foreach ($groups as $index => $group) {
                $parent = MenuItem::query()->create([
                    'type' => MenuItemType::Group,
                    'label' => $group['label'],
                    'location' => MenuLocation::Footer,
                    'locale' => $locale,
                    'position' => $index + 1,
                ]);

                foreach ($group['children'] as $position => $child) {
                    MenuItem::query()->create([
                        ...$child,
                        'parent_id' => $parent->id,
                        'location' => MenuLocation::Footer,
                        'locale' => $locale,
                        'position' => $position + 1,
                    ]);
                }
            }
        });
    }

    /**
     * Brand promise, contact details and social links of the showcase,
     * stored through the audited settings action. Skipped once a person
     * saved a real contact e-mail (one outside the reserved example domains
     * used by the demo and this showcase).
     */
    private function seedSiteSettings(LocalizationConfig $config, UpdateSiteSettings $settings): void
    {
        $current = SiteSetting::query()->find(SiteSetting::SINGLETON_ID);

        $email = $current?->contact_email;

        if ($email !== null && ! str_ends_with($email, '@example.com') && ! str_ends_with($email, '.example')) {
            return;
        }

        $translations = [];
        foreach ($config->getPublicLocales() as $locale) {
            $t = fn (string $polish, string $english, string $german): string => SoftwareHouseContent::pick($locale, $polish, $english, $german);
            $translations[$locale] = new SiteSettingTranslationInputData(
                tagline: $t('Projektujemy i budujemy strony oraz aplikacje, które pracują na Twój biznes.', 'We design and build websites and web apps that work for your business.', 'Wir gestalten und entwickeln Websites und Web-Apps, die für Ihr Unternehmen arbeiten.'),
                footerText: $t('Punkt Startowy. Wszystkie prawa zastrzeżone.', 'Punkt Startowy. All rights reserved.', 'Punkt Startowy. Alle Rechte vorbehalten.'),
                seoTitle: $t('Software house: strony i aplikacje internetowe', 'Software house: websites and web apps', 'Softwarehaus: Websites und Web-Apps'),
                seoDescription: $t('Projektujemy, budujemy i utrzymujemy strony firmowe, sklepy i aplikacje webowe.', 'We design, build and maintain company websites, online shops and web apps.', 'Wir gestalten, entwickeln und betreuen Firmenwebsites, Onlineshops und Web-Apps.'),
            );
        }

        $settings->handle(
            new SiteSettingsInputData(
                siteName: 'Punkt Startowy',
                logoMediaId: null,
                ogImageMediaId: SoftwareHouseMediaSeeder::assetId('hero'),
                contactEmail: self::CONTACT_EMAIL,
                contactPhone: '+48 600 000 000',
                addressLine: 'ul. Przykładowa 12',
                postalCode: '00-001',
                city: 'Warszawa',
                countryCode: null,
                contactRecipientEmail: 'skrzynka@example.com',
                socialLinks: [
                    'linkedin' => 'https://www.linkedin.com/company/example',
                    'github' => 'https://github.com/example',
                    'instagram' => 'https://www.instagram.com/example',
                ],
                translations: $translations,
            ),
            User::query()->where('email', DemoContent::USER_EMAIL)->first(),
            $current?->updated_at?->toIso8601String(),
        );
    }
}
