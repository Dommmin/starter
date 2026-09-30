<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Local sample articles: published, scheduled and draft translations in one
 * or more languages, very short and very long titles, empty optional fields,
 * long rich text and covers from {@see DemoMediaSeeder}. Slugs come from the
 * {@see DemoContent} registry used by `--remove-demo`. Idempotent: an article
 * whose first seeded slug already exists is skipped.
 */
class ArticleSeeder extends Seeder
{
    /**
     * Per article: optional cover (DemoMediaSeeder key), long body flag and
     * translations as `locale => [title, excerpt, state, days from now]`,
     * where state is `published`, `scheduled` or `draft`.
     *
     * @var array<string, array{cover?: string, long?: bool, translations: array<string, array{0: string, 1: string|null, 2: string, 3: int}>}>
     */
    public const array ARTICLES = [
        'welcome' => ['translations' => [
            'en' => ['Welcome to our news', 'This sample article shows how news is listed and rendered.', 'published', -1],
            'pl' => ['Witamy w aktualnościach', 'Ten przykładowy artykuł pokazuje listę i widok aktualności.', 'published', -1],
        ]],
        'scheduled' => ['translations' => [
            'en' => ['Coming next week', 'Scheduled articles stay hidden until their publication date.', 'scheduled', 7],
        ]],
        'draft' => ['translations' => [
            'en' => ['Draft ideas', null, 'draft', 0],
        ]],
        'short' => ['translations' => [
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
        'accessibility' => ['long' => true, 'translations' => [
            'en' => ['Accessibility first', 'Keyboard, contrast and alternative text in practice.', 'published', -6],
            'pl' => ['Dostępność przede wszystkim', 'Klawiatura, kontrast i tekst alternatywny w praktyce.', 'published', -6],
        ]],
        'performance' => ['cover' => 'portrait', 'translations' => [
            'en' => ['Faster pages in five steps', 'Small changes that make a visible difference.', 'published', -8],
            'pl' => ['Szybsze strony w pięciu krokach', 'Drobne zmiany, które widać gołym okiem.', 'published', -8],
        ]],
        'security' => ['long' => true, 'translations' => [
            'en' => ['Security checklist', null, 'published', -10],
            'pl' => ['Lista kontrolna bezpieczeństwa', null, 'published', -10],
        ]],
        'team' => ['cover' => 'team', 'translations' => [
            'en' => ['Meet the team', 'The people behind the project.', 'published', -12],
            'pl' => ['Poznaj zespół', 'Ludzie, którzy stoją za projektem.', 'published', -12],
        ]],
        'case-study' => ['long' => true, 'translations' => [
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
            'en' => ['Autumn events', 'Workshops, meetups and a picnic.', 'published', -25],
            'pl' => ['Jesienne wydarzenia', 'Warsztaty, spotkania i piknik.', 'published', -25],
        ]],
        'newsletter' => ['translations' => [
            'en' => ['Newsletter: September', 'Monthly summary.', 'published', -30],
            'pl' => ['Newsletter: wrzesień', 'Podsumowanie miesiąca.', 'published', -30],
        ]],
        'faq-roundup' => ['long' => true, 'translations' => [
            'en' => ['Your questions answered', 'The most common questions from our readers.', 'published', -35],
            'pl' => ['Odpowiadamy na pytania', 'Najczęstsze pytania od czytelników.', 'published', -35],
        ]],
        'tips' => ['cover' => 'webp', 'translations' => [
            'en' => ['Ten quick tips', 'Five minutes each.', 'published', -40],
            'pl' => ['Dziesięć szybkich porad', 'Każda zajmie pięć minut.', 'published', -40],
        ]],
        'archive' => ['translations' => [
            'en' => ['From the archive', 'A look back at our first year.', 'published', -60],
            'pl' => ['Z archiwum', 'Wspomnienie pierwszego roku.', 'published', -60],
        ]],
        'launch' => ['cover' => 'landscape', 'translations' => [
            'en' => ['Launch countdown', 'Scheduled for later this week.', 'scheduled', 3],
            'pl' => ['Odliczanie do startu', 'Zaplanowane na koniec tygodnia.', 'scheduled', 3],
        ]],
        'polish-draft' => ['translations' => [
            'pl' => ['Szkic artykułu o żółwiach', null, 'draft', 0],
        ]],
        'mixed-status' => ['translations' => [
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
            $firstLocale = (string) array_key_first($slugs);

            if (ArticleTranslation::query()->where('locale', $firstLocale)->where('slug', $slugs[$firstLocale])->exists()) {
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
                    'body' => ($definition['long'] ?? false)
                        ? DemoDocument::long($locale, $excerpt ?? $title)
                        : DemoDocument::short($excerpt ?? $title),
                    'status' => $state === 'draft' ? PublicationStatus::Draft : PublicationStatus::Published,
                    'published_at' => self::publishedAt($state, $days),
                ]);
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
     * Registered `locale => slug` map of a sample key.
     *
     * @return array<string, string>
     */
    private static function slugs(string $key): array
    {
        return DemoContent::ARTICLES[$key];
    }
}
