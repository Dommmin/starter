<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state: a tag named in English and Polish.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'slug' => substr(fake()->unique()->slug(2), 0, 60),
            'name' => ['en' => ucfirst($word), 'pl' => ucfirst($word).' PL'],
        ];
    }
}
