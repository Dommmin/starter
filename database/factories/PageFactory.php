<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A bare page has no translations; use `published()` or `draft()` to
     * attach a translation in the default public locale.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Attach a published translation in the default public locale.
     */
    public function published(): static
    {
        return $this->has(PageTranslation::factory()->published(), 'translations');
    }

    /**
     * Attach a draft translation in the default public locale.
     */
    public function draft(): static
    {
        return $this->has(PageTranslation::factory()->draft(), 'translations');
    }
}
