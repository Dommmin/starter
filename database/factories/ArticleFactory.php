<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A bare article has no translations; use `published()` or `draft()` to
     * attach a translation in the default public locale.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cover_media_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Attach a published translation in the default public locale.
     */
    public function published(): static
    {
        return $this->has(ArticleTranslation::factory()->published(), 'translations');
    }

    /**
     * Attach a draft translation in the default public locale.
     */
    public function draft(): static
    {
        return $this->has(ArticleTranslation::factory()->draft(), 'translations');
    }
}
