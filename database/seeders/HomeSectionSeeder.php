<?php

namespace Database\Seeders;

use App\Actions\Home\EnsureHomeSections;
use App\Data\Home\ContactContentData;
use App\Data\Home\CtaContentData;
use App\Data\Home\FaqContentData;
use App\Data\Home\FeatureItemData;
use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\HomeActionData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\TestimonialItemData;
use App\Data\Home\TestimonialsContentData;
use App\Enums\HomeIcon;
use App\Enums\HomeLinkTarget;
use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Database\Seeder;
use Spatie\LaravelData\Data;

/**
 * Demo content of the home page: the former static landing page rebuilt from
 * `lang/{locale}/public.php` (`landing.*`) for every public locale. Hero,
 * features, contact and CTA start enabled; FAQ, testimonials and latest
 * articles start disabled. The local environment, where the sample FAQs and
 * articles exist, uses the copy of the demo studio (self::BRAND) for hero,
 * features and CTA and a per-locale order and visibility (self::LOCAL_LAYOUT).
 * Idempotent: existing sections are kept as edited.
 */
class HomeSectionSeeder extends Seeder
{
    /**
     * Types whose demo section starts enabled.
     *
     * @var list<HomeSectionType>
     */
    public const array ENABLED_TYPES = [
        HomeSectionType::Hero,
        HomeSectionType::Features,
        HomeSectionType::Contact,
        HomeSectionType::Cta,
    ];

    /**
     * Local order and disabled types per locale, so the panel and the home
     * page show different arrangements; other locales use the default order
     * with every section enabled.
     *
     * @var array<string, array{order: list<HomeSectionType>, disabled: list<HomeSectionType>}>
     */
    public const array LOCAL_LAYOUT = [
        'en' => [
            'order' => [HomeSectionType::Hero, HomeSectionType::LatestArticles, HomeSectionType::Features, HomeSectionType::Testimonials, HomeSectionType::Faq, HomeSectionType::Contact, HomeSectionType::Cta],
            'disabled' => [],
        ],
        'de' => [
            'order' => [HomeSectionType::Hero, HomeSectionType::Features, HomeSectionType::Contact, HomeSectionType::Cta, HomeSectionType::Faq, HomeSectionType::Testimonials, HomeSectionType::LatestArticles],
            'disabled' => [HomeSectionType::Faq, HomeSectionType::Testimonials, HomeSectionType::LatestArticles],
        ],
    ];

    /**
     * Local copy of the demo studio per locale (English for other locales).
     *
     * @var array<string, array{eyebrow: string, title: string, description: string, contact: string, articles: string, featuresTitle: string, featuresDescription: string, features: list<array{0: string, 1: string}>, ctaTitle: string, ctaDescription: string}>
     */
    public const array BRAND = [
        'pl' => [
            'eyebrow' => 'Pracownia z Łodzi',
            'title' => 'Strony internetowe dla małych firm, gotowe w tydzień',
            'description' => 'Projektujemy, wdrażamy i utrzymujemy szybkie, dostępne strony dla piekarni, gabinetów i stowarzyszeń. Treści edytujesz samodzielnie, bez programisty.',
            'contact' => 'Napisz do nas',
            'articles' => 'Czytaj aktualności',
            'featuresTitle' => 'Co dla Ciebie zrobimy',
            'featuresDescription' => 'Jeden zespół od projektu po opiekę po starcie.',
            'features' => [
                ['Projekt dopasowany do marki', 'Kolory, typografia i zdjęcia, które pasują do Twojej firmy.'],
                ['Bezpieczeństwo i kopie zapasowe', 'Aktualizacje, dwuetapowe logowanie i codzienne kopie danych.'],
                ['Szybkie ładowanie', 'Strona otwiera się błyskawicznie także na słabym zasięgu.'],
                ['Dostępność dla wszystkich', 'Obsługa klawiaturą, czytelny kontrast i teksty alternatywne.'],
            ],
            'ctaTitle' => 'Porozmawiajmy o Twojej stronie',
            'ctaDescription' => 'Opisz krótko, czego potrzebujesz. Wycenę prześlemy w ciągu jednego dnia roboczego.',
        ],
        'en' => [
            'eyebrow' => 'A studio from Łódź',
            'title' => 'Websites for small businesses, ready in a week',
            'description' => 'We design, build and maintain fast, accessible websites for bakeries, clinics and associations. You edit the content yourself, no developer needed.',
            'contact' => 'Get in touch',
            'articles' => 'Read our news',
            'featuresTitle' => 'What we do for you',
            'featuresDescription' => 'One team from the first sketch to support after launch.',
            'features' => [
                ['Design that fits your brand', 'Colours, typography and photos that suit your business.'],
                ['Security and backups', 'Updates, two-factor sign-in and daily backups.'],
                ['Fast loading', 'Pages open instantly, even on a weak connection.'],
                ['Accessible to everyone', 'Keyboard support, readable contrast and alternative text.'],
            ],
            'ctaTitle' => "Let's talk about your website",
            'ctaDescription' => 'Tell us briefly what you need. We send a quote within one business day.',
        ],
        'de' => [
            'eyebrow' => 'Ein Studio aus Łódź',
            'title' => 'Websites für kleine Unternehmen, fertig in einer Woche',
            'description' => 'Wir gestalten, bauen und betreuen schnelle, barrierefreie Websites für Bäckereien, Praxen und Vereine. Inhalte pflegen Sie selbst, ganz ohne Entwickler.',
            'contact' => 'Kontakt aufnehmen',
            'articles' => 'Neuigkeiten lesen',
            'featuresTitle' => 'Was wir für Sie tun',
            'featuresDescription' => 'Ein Team vom ersten Entwurf bis zur Betreuung nach dem Start.',
            'features' => [
                ['Design passend zur Marke', 'Farben, Schriften und Fotos, die zu Ihrem Unternehmen passen.'],
                ['Sicherheit und Backups', 'Updates, Zwei-Faktor-Anmeldung und tägliche Sicherungen.'],
                ['Schnelles Laden', 'Seiten öffnen sich sofort, auch bei schwachem Empfang.'],
                ['Barrierefrei für alle', 'Tastaturbedienung, guter Kontrast und Alternativtexte.'],
            ],
            'ctaTitle' => 'Sprechen wir über Ihre Website',
            'ctaDescription' => 'Beschreiben Sie kurz, was Sie brauchen. Ein Angebot erhalten Sie innerhalb eines Werktags.',
        ],
    ];

    public function run(EnsureHomeSections $ensureHomeSections, LocalizationConfig $localization): void
    {
        if (app()->environment('local')) {
            foreach ($localization->getPublicLocales() as $locale) {
                foreach (self::defaults($locale) as $type => $definition) {
                    HomeSection::query()->firstOrCreate(
                        ['locale' => $locale, 'type' => HomeSectionType::from($type)],
                        $definition,
                    );
                }
            }
        }

        $ensureHomeSections->handle(
            fn (HomeSectionType $type, string $locale): Data => self::landingContent($type, $locale),
            self::enabledTypes(),
        );
    }

    /**
     * Types whose demo section starts enabled in the current environment:
     * all of them locally (sample FAQs and articles are seeded there),
     * otherwise self::ENABLED_TYPES.
     *
     * @return list<HomeSectionType>
     */
    public static function enabledTypes(): array
    {
        return app()->environment('local') ? HomeSectionType::cases() : self::ENABLED_TYPES;
    }

    /**
     * Whether the demo section of a type starts enabled in a locale.
     */
    public static function startsEnabled(HomeSectionType $type, string $locale): bool
    {
        if (app()->environment('local')) {
            return ! in_array($type, self::LOCAL_LAYOUT[$locale]['disabled'] ?? [], true);
        }

        return in_array($type, self::ENABLED_TYPES, true);
    }

    /**
     * Demo position (1-based) of a type in a locale.
     */
    public static function position(HomeSectionType $type, string $locale): int
    {
        $order = app()->environment('local') ? (self::LOCAL_LAYOUT[$locale]['order'] ?? null) : null;

        return $order === null ? $type->defaultPosition() : (int) array_search($type, $order, true) + 1;
    }

    /**
     * Demo definition of every section of a locale, keyed by type value:
     * the single source for the seeder and a demo-content registry (e.g.
     * init-project `--remove-demo`).
     *
     * @return array<string, array{enabled: bool, position: int, content: Data}>
     */
    public static function defaults(string $locale): array
    {
        $defaults = [];
        foreach (HomeSectionType::cases() as $type) {
            $defaults[$type->value] = [
                'enabled' => self::startsEnabled($type, $locale),
                'position' => self::position($type, $locale),
                'content' => self::landingContent($type, $locale),
            ];
        }

        return $defaults;
    }

    /**
     * Whether the section still holds exactly the demo content of its type
     * and locale (no heuristic: equality with the definition above).
     */
    public static function isDemo(HomeSection $section): bool
    {
        return $section->content->toArray() === self::landingContent($section->type, $section->locale)->toArray();
    }

    public static function landingContent(HomeSectionType $type, string $locale): Data
    {
        if (app()->environment('local') && in_array($type, [HomeSectionType::Hero, HomeSectionType::Features, HomeSectionType::Cta], true)) {
            return self::brandContent($type, self::BRAND[$locale] ?? self::BRAND['en']);
        }

        $t = fn (string $key): string => __($key, [], $locale);

        return match ($type) {
            HomeSectionType::Hero => new HeroContentData(
                title: $t('public.landing.heroTitle'),
                eyebrow: $t('public.landing.badge'),
                description: $t('public.landing.heroDescription'),
                primaryAction: new HomeActionData(label: $t('public.landing.ctaPrimaryGuest'), target: HomeLinkTarget::Register),
                secondaryAction: new HomeActionData(label: $t('common.nav.login'), target: HomeLinkTarget::Login),
            ),
            HomeSectionType::Features => new FeaturesContentData(
                items: [
                    new FeatureItemData(title: $t('public.landing.feature1Title'), description: $t('public.landing.feature1Desc'), icon: HomeIcon::Palette),
                    new FeatureItemData(title: $t('public.landing.feature2Title'), description: $t('public.landing.feature2Desc'), icon: HomeIcon::Lock),
                    new FeatureItemData(title: $t('public.landing.feature3Title'), description: $t('public.landing.feature3Desc'), icon: HomeIcon::Zap),
                    new FeatureItemData(title: $t('public.landing.feature4Title'), description: $t('public.landing.feature4Desc'), icon: HomeIcon::Accessibility),
                ],
                title: $t('public.landing.featuresHeading'),
                description: $t('public.landing.featuresSubheading'),
            ),
            HomeSectionType::Faq => new FaqContentData(
                title: $t('public.landing.faqTitle'),
                description: $t('public.landing.faqDescription'),
            ),
            HomeSectionType::Testimonials => new TestimonialsContentData(
                items: [
                    new TestimonialItemData(author: $t('public.landing.testimonial1Author'), quote: $t('public.landing.testimonial1Quote'), role: $t('public.landing.testimonial1Role')),
                    new TestimonialItemData(author: $t('public.landing.testimonial2Author'), quote: $t('public.landing.testimonial2Quote'), role: $t('public.landing.testimonial2Role')),
                    new TestimonialItemData(author: $t('public.landing.testimonial3Author'), quote: $t('public.landing.testimonial3Quote')),
                ],
                title: $t('public.landing.testimonialsTitle'),
            ),
            HomeSectionType::LatestArticles => new LatestArticlesContentData(
                limit: 3,
                title: $t('public.landing.latestArticlesTitle'),
            ),
            HomeSectionType::Contact => new ContactContentData(
                title: $t('public.contact.title'),
                description: $t('public.contact.description'),
            ),
            HomeSectionType::Cta => new CtaContentData(
                title: $t('public.landing.ctaBottomTitle'),
                description: $t('public.landing.ctaBottomDescription'),
                primaryAction: new HomeActionData(label: $t('public.landing.ctaPrimaryGuest'), target: HomeLinkTarget::Register),
                secondaryAction: new HomeActionData(label: $t('common.nav.login'), target: HomeLinkTarget::Login),
            ),
        };
    }

    /**
     * Hero, features or CTA of the demo studio.
     *
     * @param  array{eyebrow: string, title: string, description: string, contact: string, articles: string, featuresTitle: string, featuresDescription: string, features: list<array{0: string, 1: string}>, ctaTitle: string, ctaDescription: string}  $copy
     */
    private static function brandContent(HomeSectionType $type, array $copy): Data
    {
        $icons = [HomeIcon::Palette, HomeIcon::Lock, HomeIcon::Zap, HomeIcon::Accessibility];

        return match ($type) {
            HomeSectionType::Features => new FeaturesContentData(
                items: array_map(
                    fn (array $feature, HomeIcon $icon): FeatureItemData => new FeatureItemData(title: $feature[0], description: $feature[1], icon: $icon),
                    $copy['features'],
                    $icons,
                ),
                title: $copy['featuresTitle'],
                description: $copy['featuresDescription'],
            ),
            HomeSectionType::Cta => new CtaContentData(
                title: $copy['ctaTitle'],
                description: $copy['ctaDescription'],
                primaryAction: new HomeActionData(label: $copy['contact'], target: HomeLinkTarget::Contact),
                secondaryAction: new HomeActionData(label: $copy['articles'], target: HomeLinkTarget::Articles),
            ),
            default => new HeroContentData(
                title: $copy['title'],
                eyebrow: $copy['eyebrow'],
                description: $copy['description'],
                primaryAction: new HomeActionData(label: $copy['contact'], target: HomeLinkTarget::Contact),
                secondaryAction: new HomeActionData(label: $copy['articles'], target: HomeLinkTarget::Articles),
            ),
        };
    }
}
