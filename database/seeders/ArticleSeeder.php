<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Article;
use Illuminate\Database\Seeder;

/**
 * Local sample content: a published article with translations, a scheduled
 * one and a draft. Slugs come from the {@see DemoContent} registry used by
 * `--remove-demo`.
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
                'slug' => DemoContent::ARTICLES['welcome']['en'],
                'excerpt' => 'This sample article shows how news is listed and rendered.',
                'body' => self::document('Published articles appear in the list, the sitemap and search results.'),
                'status' => PublicationStatus::Published,
                'published_at' => now()->subDay(),
            ],
            [
                'locale' => 'pl',
                'title' => 'Witamy w aktualnościach',
                'slug' => DemoContent::ARTICLES['welcome']['pl'],
                'excerpt' => 'Ten przykładowy artykuł pokazuje listę i widok aktualności.',
                'body' => self::document('Opublikowane artykuły trafiają na listę, do mapy strony i wyników wyszukiwania.'),
                'status' => PublicationStatus::Published,
                'published_at' => now()->subDay(),
            ],
        ]);

        Article::query()->create()->translations()->create([
            'locale' => 'en',
            'title' => 'Coming next week',
            'slug' => DemoContent::ARTICLES['scheduled']['en'],
            'excerpt' => 'Scheduled articles stay hidden until their publication date.',
            'body' => self::document('This text becomes visible next week.'),
            'status' => PublicationStatus::Published,
            'published_at' => now()->addWeek()->startOfDay(),
        ]);

        Article::query()->create()->translations()->create([
            'locale' => 'en',
            'title' => 'Draft ideas',
            'slug' => DemoContent::ARTICLES['draft']['en'],
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
