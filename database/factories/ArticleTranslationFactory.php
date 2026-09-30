<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ArticleTranslation;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ArticleTranslation>
 */
class ArticleTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'article_id' => Article::factory(),
            'locale' => config('localization.public_default', 'en'),
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(15),
            'meta_description' => fake()->sentence(12),
            'body' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => fake()->paragraph()]],
                    ],
                ],
            ],
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the translation is visible to visitors.
     */
    public function published(?DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublicationStatus::Published,
            'published_at' => $at ?? now(),
        ]);
    }

    /**
     * Indicate that the translation is published but scheduled for later.
     */
    public function scheduled(): static
    {
        return $this->published(now()->addDay());
    }

    /**
     * Indicate that the translation is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Set the translation locale.
     */
    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'locale' => $locale,
        ]);
    }
}
