<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PageTranslation>
 */
class PageTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'page_id' => Page::factory(),
            'locale' => config('localization.public_default', 'en'),
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
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
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
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
