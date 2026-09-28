<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Article;
use Illuminate\Database\Seeder;

/**
 * Local sample content: a published article with translations, a scheduled
 * one and a draft.
 */
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $published = Article::query()->create();
        $published->translations()->createMany([
            [
                'locale' => 'en',
                'title' => 'Welcome to our news',
                'slug' => 'welcome-to-our-news',
                'excerpt' => 'This sample article shows how news is listed and rendered.',
                'body' => self::document('Published articles appear in the list, the sitemap and search results.'),
                'status' => PublicationStatus::Published,
                'published_at' => now()->subDay(),
            ],
            [
                'locale' => 'pl',
                'title' => 'Witamy w aktualnościach',
                'slug' => 'witamy-w-aktualnosciach',
                'excerpt' => 'Ten przykładowy artykuł pokazuje listę i widok aktualności.',
                'body' => self::document('Opublikowane artykuły trafiają na listę, do mapy strony i wyników wyszukiwania.'),
                'status' => PublicationStatus::Published,
                'published_at' => now()->subDay(),
            ],
        ]);

        Article::query()->create()->translations()->create([
            'locale' => 'en',
            'title' => 'Coming next week',
            'slug' => 'coming-next-week',
            'excerpt' => 'Scheduled articles stay hidden until their publication date.',
            'body' => self::document('This text becomes visible next week.'),
            'status' => PublicationStatus::Published,
            'published_at' => now()->addWeek()->startOfDay(),
        ]);

        Article::query()->create()->translations()->create([
            'locale' => 'en',
            'title' => 'Draft ideas',
            'slug' => 'draft-ideas',
            'body' => self::document('Draft content is never visible to visitors.'),
            'status' => PublicationStatus::Draft,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function document(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ],
        ];
    }
}
