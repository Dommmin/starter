<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSettingTranslation>
 */
class SiteSettingTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_setting_id' => SiteSetting::factory(),
            'locale' => config('localization.public_default', 'en'),
            'tagline' => fake()->sentence(4),
            'footer_text' => fake()->sentence(6),
            'seo_title' => fake()->sentence(3),
            'seo_description' => fake()->sentence(12),
        ];
    }

    /**
     * Indicate the language of the translation.
     */
    public function locale(string $locale): static
    {
        return $this->state(fn (): array => ['locale' => $locale]);
    }
}
