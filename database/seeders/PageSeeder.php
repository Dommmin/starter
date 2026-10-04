<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageSlugRedirect;
use App\Models\PageTranslation;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Local sample pages: published, scheduled and draft translations, pages in
 * one to three languages, empty meta descriptions, long rich text and 301
 * redirects from former slugs; enough pages for two admin list pages. Slugs
 * come from the {@see DemoContent} registry used by `--remove-demo`.
 * Idempotent: a page with any registered slug already present is skipped.
 */
class PageSeeder extends Seeder
{
    /**
     * Per page: long body flag and translations as
     * `locale => [title, meta description, state, days from now]`, where
     * state is `published`, `scheduled` or `draft`.
     *
     * @var array<string, array{long?: bool, translations: array<string, array{0: string, 1: string|null, 2: string, 3: int}>}>
     */
    public const array PAGES = [
        'privacy' => ['long' => true, 'translations' => [
            'en' => ['Privacy policy', 'How this website processes personal data.', 'published', 0],
            'pl' => ['Polityka prywatności', 'Jak ta strona przetwarza dane osobowe.', 'published', 0],
        ]],
        'upcoming' => ['translations' => [
            'en' => ['Upcoming offer', null, 'draft', 0],
        ]],
        'about' => ['long' => true, 'translations' => [
            'de' => ['Über uns', 'Wer wir sind und was wir tun.', 'published', -30],
            'en' => ['About us', 'Who we are and what we do.', 'published', -30],
            'pl' => ['O nas', 'Kim jesteśmy i czym się zajmujemy.', 'published', -30],
        ]],
        'terms' => ['long' => true, 'translations' => [
            'en' => ['Terms of service for the website, the newsletter and all online forms available to visitors', 'Rules for using this website.', 'published', -20],
            'pl' => ['Regulamin korzystania z serwisu internetowego, newslettera oraz wszystkich formularzy dostępnych dla odwiedzających', 'Zasady korzystania z serwisu.', 'published', -20],
        ]],
        'cookies' => ['translations' => [
            'pl' => ['Polityka cookies', null, 'published', -20],
        ]],
        'contact-details' => ['translations' => [
            'en' => ['Contact details', 'Address, phone and opening hours.', 'published', -10],
            'pl' => ['Dane kontaktowe', 'Adres, telefon i godziny otwarcia.', 'published', -10],
        ]],
        'careers' => ['long' => true, 'translations' => [
            'en' => ['Careers', 'Join our team.', 'scheduled', 5],
            'pl' => ['Kariera', 'Dołącz do zespołu.', 'scheduled', 5],
        ]],
        'accessibility' => ['translations' => [
            'en' => ['Accessibility statement', null, 'draft', 0],
            'pl' => ['Deklaracja dostępności', 'Informacje o dostępności serwisu.', 'published', -5],
        ]],
        'short' => ['translations' => [
            'en' => ['Ok', null, 'published', -1],
            'pl' => ['Ok', null, 'published', -1],
        ]],
        'services' => ['long' => true, 'translations' => [
            'de' => ['Leistungen', 'Websites, Pflege und Hosting für kleine Unternehmen.', 'published', -40],
            'en' => ['Services', 'Websites, maintenance and hosting for small businesses.', 'published', -40],
            'pl' => ['Usługi', 'Strony, opieka techniczna i hosting dla małych firm.', 'published', -40],
        ]],
        'pricing' => ['translations' => [
            'en' => ['Pricing', 'Three simple packages with a fixed monthly price.', 'published', -35],
            'pl' => ['Cennik', 'Trzy proste pakiety ze stałą miesięczną opłatą.', 'published', -35],
        ]],
        'portfolio' => ['long' => true, 'translations' => [
            'en' => ['Portfolio', 'Selected projects for bakeries, clinics and associations.', 'published', -28],
            'pl' => ['Realizacje', 'Wybrane projekty dla piekarni, gabinetów i stowarzyszeń.', 'published', -28],
        ]],
        'imprint' => ['translations' => [
            'de' => ['Impressum', 'Angaben gemäß § 5 DDG.', 'published', -50],
            'en' => ['Imprint', 'Legal information about the website operator.', 'published', -50],
        ]],
        'how-we-work' => ['long' => true, 'translations' => [
            'en' => ['How we work', 'From the first call to the launch in four steps.', 'published', -14],
            'pl' => ['Jak pracujemy', 'Od pierwszej rozmowy do startu w czterech krokach.', 'published', -14],
        ]],
        'partners' => ['translations' => [
            'pl' => ['Partnerzy', 'Firmy, z którymi współpracujemy na co dzień.', 'published', -12],
        ]],
        'press' => ['translations' => [
            'en' => ['Press kit', null, 'draft', 0],
            'pl' => ['Dla prasy', null, 'draft', 0],
        ]],
        'workshops' => ['translations' => [
            'de' => ['Workshops für Vereine', 'Termine im Herbst.', 'scheduled', 10],
            'en' => ['Workshops for associations', 'Autumn dates.', 'scheduled', 10],
            'pl' => ['Warsztaty dla stowarzyszeń', 'Terminy jesienne.', 'scheduled', 10],
        ]],
    ];

    /**
     * Former slugs of seeded translations as `former slug => [page key,
     * locale]`; each one redirects (301) to the current slug.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array REDIRECTS = [
        'who-we-are' => ['about', 'en'],
        'kim-jestesmy' => ['about', 'pl'],
        'oferta' => ['services', 'pl'],
    ];

    public function run(): void
    {
        foreach (self::PAGES as $key => $definition) {
            $slugs = self::slugs($key);

            if (self::seeded($slugs)) {
                continue;
            }

            $page = Page::query()->create();

            foreach ($definition['translations'] as $locale => [$title, $metaDescription, $state, $days]) {
                $page->translations()->create([
                    'locale' => $locale,
                    'title' => $title,
                    'slug' => $slugs[$locale],
                    'meta_description' => $metaDescription,
                    'body' => DemoDocument::for($locale, $title, $metaDescription, $definition['long'] ?? false),
                    'status' => $state === 'draft' ? PublicationStatus::Draft : PublicationStatus::Published,
                    'published_at' => self::publishedAt($state, $days),
                ]);
            }
        }

        foreach (self::REDIRECTS as $formerSlug => [$key, $locale]) {
            $translationId = PageTranslation::query()
                ->where('locale', $locale)
                ->where('slug', self::slugs($key)[$locale])
                ->value('id');

            if (is_int($translationId)) {
                PageSlugRedirect::query()->firstOrCreate(
                    ['locale' => $locale, 'old_slug' => $formerSlug],
                    ['page_translation_id' => $translationId],
                );
            }
        }
    }

    /**
     * Publication date of a sample translation: none for a draft, the start
     * of a future day when scheduled, otherwise `$days` from now.
     */
    private static function publishedAt(string $state, int $days): ?CarbonInterface
    {
        return match ($state) {
            'draft' => null,
            'scheduled' => now()->addDays($days)->startOfDay(),
            default => now()->addDays($days),
        };
    }

    /**
     * Whether any registered translation of a sample already exists (a
     * translation added to the registry later does not duplicate it).
     *
     * @param  array<string, string>  $slugs
     */
    private static function seeded(array $slugs): bool
    {
        foreach ($slugs as $locale => $slug) {
            if (PageTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registered `locale => slug` map of a sample key.
     *
     * @return array<string, string>
     */
    private static function slugs(string $key): array
    {
        return DemoContent::PAGES[$key];
    }
}
