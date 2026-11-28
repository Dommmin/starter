<?php

namespace Database\Factories;

use App\Actions\Home\EnsureHomeSections;
use App\Enums\HomeSectionType;
use App\Models\ContentBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentBlock>
 */
class ContentBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'locale' => 'en',
            'type' => HomeSectionType::Cta,
            'content' => EnsureHomeSections::placeholder(HomeSectionType::Cta, 'en'),
        ];
    }
}
