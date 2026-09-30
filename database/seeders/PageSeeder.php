<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Local sample content: one published page with translations and one draft.
 * Slugs come from the {@see DemoContent} registry used by `--remove-demo`.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $published = Page::query()->create();
        $published->translations()->createMany([
            [
                'locale' => 'en',
                'title' => 'Privacy policy',
                'slug' => DemoContent::PAGES['privacy']['en'],
                'meta_description' => 'How this website processes personal data.',
                'body' => self::document('This sample page shows how published content is rendered.'),
                'status' => PublicationStatus::Published,
                'published_at' => now(),
            ],
            [
                'locale' => 'pl',
                'title' => 'Polityka prywatności',
                'slug' => DemoContent::PAGES['privacy']['pl'],
                'meta_description' => 'Jak ta strona przetwarza dane osobowe.',
                'body' => self::document('Ta przykładowa strona pokazuje renderowanie opublikowanej treści.'),
                'status' => PublicationStatus::Published,
                'published_at' => now(),
            ],
        ]);

        $draft = Page::query()->create();
        $draft->translations()->create([
            'locale' => 'en',
            'title' => 'Upcoming offer',
            'slug' => DemoContent::PAGES['upcoming']['en'],
            'meta_description' => null,
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
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Overview']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ],
        ];
    }
}
