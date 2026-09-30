<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Local sample pages: published, scheduled and draft translations, pages in
 * one to three languages, empty meta descriptions and long rich text. Slugs
 * come from the {@see DemoContent} registry used by `--remove-demo`.
 * Idempotent: a page whose first seeded slug already exists is skipped.
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
    ];

    public function run(): void
    {
        foreach (self::PAGES as $key => $definition) {
            $slugs = self::slugs($key);
            $firstLocale = (string) array_key_first($slugs);

            if (PageTranslation::query()->where('locale', $firstLocale)->where('slug', $slugs[$firstLocale])->exists()) {
                continue;
            }

            $page = Page::query()->create();

            foreach ($definition['translations'] as $locale => [$title, $metaDescription, $state, $days]) {
                $page->translations()->create([
                    'locale' => $locale,
                    'title' => $title,
                    'slug' => $slugs[$locale],
                    'meta_description' => $metaDescription,
                    'body' => ($definition['long'] ?? false)
                        ? DemoDocument::long($locale, $metaDescription ?? $title)
                        : DemoDocument::short($metaDescription ?? $title),
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
        return DemoContent::PAGES[$key];
    }
}
