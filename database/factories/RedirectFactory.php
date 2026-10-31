<?php

namespace Database\Factories;

use App\Enums\RedirectStatus;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_path' => '/old/'.fake()->unique()->slug(3).'.html',
            'target' => '/'.fake()->unique()->slug(2),
            'status_code' => RedirectStatus::Permanent,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function temporary(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => RedirectStatus::Temporary,
        ]);
    }
}
