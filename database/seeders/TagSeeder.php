<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Local sample tags assigned to sample articles of {@see ArticleSeeder}.
 * Idempotent: tags are matched by slug, links are added without removing
 * the ones an editor set. Removed by `--remove-demo` through DemoTags.
 */
class TagSeeder extends Seeder
{
    /**
     * Names per locale by slug.
     *
     * @var array<string, array<string, string>>
     */
    public const array TAGS = [
        'news' => ['pl' => 'Aktualności', 'en' => 'News', 'de' => 'Neuigkeiten'],
        'guides' => ['pl' => 'Poradniki', 'en' => 'Guides', 'de' => 'Ratgeber'],
        'case-studies' => ['pl' => 'Realizacje', 'en' => 'Case studies', 'de' => 'Fallstudien'],
    ];

    /**
     * Tag slugs per sample article key.
     *
     * @var array<string, list<string>>
     */
    public const array ARTICLE_TAGS = [
        'welcome' => ['news'],
        'release-notes' => ['news'],
        'events' => ['news'],
        'newsletter' => ['news'],
        'accessibility' => ['guides'],
        'performance' => ['guides'],
        'security' => ['guides'],
        'tips' => ['guides'],
        'case-study' => ['case-studies', 'guides'],
        'local-market' => ['case-studies'],
    ];

    public function run(): void
    {
        $tags = [];

        foreach (self::TAGS as $slug => $name) {
            $tags[$slug] = Tag::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        foreach (self::ARTICLE_TAGS as $key => $slugs) {
            $article = self::article($key);

            $article?->tags()->syncWithoutDetaching(array_map(fn (string $slug): int => $tags[$slug]->id, $slugs));
        }
    }

    /**
     * The sample article with any of its registered slugs.
     */
    private static function article(string $key): ?Article
    {
        $articleId = ArticleTranslation::query()
            ->where(function ($query) use ($key): void {
                foreach (DemoContent::ARTICLES[$key] as $locale => $slug) {
                    $query->orWhere(fn ($match) => $match->where('locale', $locale)->where('slug', $slug));
                }
            })
            ->value('article_id');

        return is_int($articleId) ? Article::query()->find($articleId) : null;
    }
}
