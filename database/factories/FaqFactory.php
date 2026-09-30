<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => fake()->words(3, true),
            'answer' => fake()->paragraph(),
            'locale' => null,
            'position' => fake()->numberBetween(1, 1000),
            'published' => fake()->boolean(),
        ];
    }
}
