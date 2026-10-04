<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\ArticleTranslation;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Local sample articles: published, scheduled and draft translations in one
 * or more languages, very short and very long titles, empty optional fields,
 * long rich text, covers from {@see DemoMediaSeeder} (a few articles stay
 * without one) and 301 redirects from former slugs. Slugs come from the
 * {@see DemoContent} registry used by `--remove-demo`. Idempotent: an article
 * with any registered slug already present is skipped, an existing redirect is
 * kept.
 */
class ArticleSeeder extends Seeder
{
    /**
     * Former slugs of seeded translations as `former slug => [article key,
     * locale]`; each one redirects (301) to the current slug.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array REDIRECTS = [
        'release-notes-v2' => ['release-notes', 'en'],
        'wydanie-2-0' => ['release-notes', 'pl'],
        'meet-our-team' => ['team', 'en'],
    ];

    /**
     * Per article: optional cover (DemoMediaSeeder key), long body flag,
     * optional short body per locale (otherwise the excerpt or title) and
     * translations as `locale => [title, excerpt, state, days from now]`,
     * where state is `published`, `scheduled` or `draft`.
     *
     * @var array<string, array{cover?: string, long?: bool, bodies?: array<string, string>, translations: array<string, array{0: string, 1: string|null, 2: string, 3: int}>}>
     */
    public const array ARTICLES = [
        'welcome' => ['cover' => 'office', 'long' => true, 'translations' => [
            'de' => ['Willkommen in unseren Neuigkeiten', 'Hier berichten wir über Projekte, Tipps und Neuigkeiten aus dem Studio.', 'published', -1],
            'en' => ['Welcome to our news', 'Projects, tips and news from the studio — published every few weeks.', 'published', -1],
            'pl' => ['Witamy w aktualnościach', 'Projekty, porady i nowości z pracowni — publikujemy co kilka tygodni.', 'published', -1],
        ]],
        'scheduled' => ['cover' => 'workshop', 'translations' => [
            'en' => ['Coming next week', 'Scheduled articles stay hidden until their publication date.', 'scheduled', 7],
        ]],
        'draft' => ['translations' => [
            'en' => ['Draft ideas', null, 'draft', 0],
        ]],
        'short' => ['cover' => 'no-alt', 'bodies' => [
            'en' => 'A two-line note: the studio is closed on Friday. We answer all messages on Monday.',
            'pl' => 'Krótka notka: w piątek pracownia jest zamknięta. Na wszystkie wiadomości odpowiemy w poniedziałek.',
        ], 'translations' => [
            'en' => ['Hi', null, 'published', -2],
            'pl' => ['Hej', null, 'published', -2],
        ]],
        'long-title' => ['cover' => 'panorama', 'long' => true, 'translations' => [
            'en' => ['A very long headline that checks wrapping in lists, cards and the browser tab, because editors sometimes write whole sentences instead of short, punchy titles', 'An excerpt that is also rather long, to show how two or three lines of text behave next to a cover image on a narrow phone screen and on a wide desktop monitor.', 'published', -3],
            'pl' => ['Bardzo długi nagłówek sprawdzający zawijanie na listach, kartach i w karcie przeglądarki, bo redaktorzy czasem piszą całe zdania zamiast krótkich tytułów', 'Zajawka, która także jest dość długa, aby pokazać, jak dwie lub trzy linie tekstu zachowują się obok okładki na wąskim telefonie i szerokim monitorze.', 'published', -3],
        ]],
        'polish-characters' => ['cover' => 'landscape', 'long' => true, 'translations' => [
            'pl' => ['Zażółć gęślą jaźń — test polskich znaków w tytule i treści', 'Ąę, ćń, óś, źż: sprawdzamy fonty, sortowanie i adresy URL.', 'published', -4],
        ]],
        'release-notes' => ['cover' => 'square', 'long' => true, 'translations' => [
            'de' => ['Versionshinweise 2.0', 'Was ist neu in Version 2.0?', 'published', -5],
            'en' => ['Release notes 2.0', 'What is new in version 2.0.', 'published', -5],
            'pl' => ['Informacje o wydaniu 2.0', 'Co nowego w wersji 2.0.', 'published', -5],
        ]],
        'accessibility' => ['cover' => 'tall', 'long' => true, 'translations' => [
            'de' => ['Barrierefreiheit zuerst', null, 'draft', 0],
            'en' => ['Accessibility first', 'Keyboard, contrast and alternative text in practice.', 'published', -6],
            'pl' => ['Dostępność przede wszystkim', 'Klawiatura, kontrast i tekst alternatywny w praktyce.', 'published', -6],
        ]],
        'performance' => ['cover' => 'portrait', 'translations' => [
            'en' => ['Faster pages in five steps', 'Small changes that make a visible difference.', 'published', -8],
            'pl' => ['Szybsze strony w pięciu krokach', 'Drobne zmiany, które widać gołym okiem.', 'published', -8],
        ]],
        'security' => ['cover' => 'workshop', 'long' => true, 'translations' => [
            'en' => ['Security checklist', null, 'published', -10],
            'pl' => ['Lista kontrolna bezpieczeństwa', null, 'published', -10],
        ]],
        'team' => ['cover' => 'team', 'translations' => [
            'de' => ['Lernen Sie das Team kennen', 'Die Menschen hinter dem Projekt.', 'published', -12],
            'en' => ['Meet the team', 'The people behind the project.', 'published', -12],
            'pl' => ['Poznaj zespół', 'Ludzie, którzy stoją za projektem.', 'published', -12],
        ]],
        'case-study' => ['cover' => 'landscape', 'long' => true, 'translations' => [
            'en' => ['Case study: a local bakery goes online', 'From a paper menu to online orders in one week.', 'published', -15],
            'pl' => ['Studium przypadku: lokalna piekarnia w sieci', 'Od papierowego menu do zamówień online w tydzień.', 'published', -15],
        ]],
        'remote-work' => ['translations' => [
            'en' => ['Remote work tools we actually use', 'A short, honest list.', 'published', -18],
        ]],
        'local-market' => ['cover' => 'small', 'translations' => [
            'pl' => ['Rynek lokalny w liczbach', 'Łódź, Poznań i Wrocław — porównanie.', 'published', -20],
        ]],
        'events' => ['cover' => 'wide', 'translations' => [
            'de' => ['Herbstveranstaltungen', 'Workshops, Treffen und ein Picknick.', 'published', -25],
            'en' => ['Autumn events', 'Workshops, meetups and a picnic.', 'published', -25],
            'pl' => ['Jesienne wydarzenia', 'Warsztaty, spotkania i piknik.', 'published', -25],
        ]],
        'newsletter' => ['translations' => [
            'en' => ['Newsletter: September', 'Monthly summary.', 'published', -30],
            'pl' => ['Newsletter: wrzesień', 'Podsumowanie miesiąca.', 'published', -30],
        ]],
        'faq-roundup' => ['cover' => 'square', 'long' => true, 'translations' => [
            'en' => ['Your questions answered', 'The most common questions from our readers.', 'published', -35],
            'pl' => ['Odpowiadamy na pytania', 'Najczęstsze pytania od czytelników.', 'published', -35],
        ]],
        'tips' => ['cover' => 'webp', 'translations' => [
            'en' => ['Ten quick tips', 'Five minutes each.', 'published', -40],
            'pl' => ['Dziesięć szybkich porad', 'Każda zajmie pięć minut.', 'published', -40],
        ]],
        'archive' => ['cover' => 'panorama', 'translations' => [
            'en' => ['From the archive', 'A look back at our first year.', 'published', -60],
            'pl' => ['Z archiwum', 'Wspomnienie pierwszego roku.', 'published', -60],
        ]],
        'launch' => ['cover' => 'landscape', 'translations' => [
            'de' => ['Countdown zum Start', 'Für Ende der Woche geplant.', 'scheduled', 3],
            'en' => ['Launch countdown', 'Scheduled for later this week.', 'scheduled', 3],
            'pl' => ['Odliczanie do startu', 'Zaplanowane na koniec tygodnia.', 'scheduled', 3],
        ]],
        'polish-draft' => ['translations' => [
            'pl' => ['Szkic artykułu o żółwiach', null, 'draft', 0],
        ]],
        'mixed-status' => ['cover' => 'office', 'translations' => [
            'en' => ['Translation in progress', 'Published in English, still a draft in Polish.', 'published', -45],
            'pl' => ['Tłumaczenie w toku', null, 'draft', 0],
        ]],
        'history' => ['cover' => 'portrait', 'long' => true, 'translations' => [
            'en' => ['Our history', null, 'published', -90],
            'pl' => ['Nasza historia', null, 'published', -90],
        ]],
    ];

    public function run(): void
    {
        foreach (self::ARTICLES as $key => $definition) {
            $slugs = self::slugs($key);

            if (self::seeded($slugs)) {
                continue;
            }

            $article = Article::query()->create([
                'cover_media_id' => isset($definition['cover']) ? DemoMediaSeeder::assetId($definition['cover']) : null,
            ]);

            foreach ($definition['translations'] as $locale => [$title, $excerpt, $state, $days]) {
                $article->translations()->create([
                    'locale' => $locale,
                    'title' => $title,
                    'slug' => $slugs[$locale],
                    'excerpt' => $excerpt,
                    'meta_description' => $excerpt,
                    'body' => self::body($definition, $locale, $title, $excerpt),
                    'status' => $state === 'draft' ? PublicationStatus::Draft : PublicationStatus::Published,
                    'published_at' => self::publishedAt($state, $days),
                ]);
            }
        }

        foreach (self::REDIRECTS as $formerSlug => [$key, $locale]) {
            $translationId = ArticleTranslation::query()
                ->where('locale', $locale)
                ->where('slug', self::slugs($key)[$locale])
                ->value('id');

            if (is_int($translationId)) {
                ArticleSlugRedirect::query()->firstOrCreate(
                    ['locale' => $locale, 'old_slug' => $formerSlug],
                    ['article_translation_id' => $translationId],
                );
            }
        }
    }

    /**
     * Rich text of a sample translation: its explicit short body if any,
     * otherwise {@see DemoDocument::for()}.
     *
     * @param  array{long?: bool, bodies?: array<string, string>}  $definition
     * @return array<string, mixed>
     */
    private static function body(array $definition, string $locale, string $title, ?string $excerpt): array
    {
        return isset($definition['bodies'][$locale])
            ? DemoDocument::short($definition['bodies'][$locale])
            : DemoDocument::for($locale, $title, $excerpt, $definition['long'] ?? false);
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
            if (ArticleTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists()) {
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
        return DemoContent::ARTICLES[$key];
    }
}
