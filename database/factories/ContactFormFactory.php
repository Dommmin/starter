<?php

namespace Database\Factories;

use App\Models\ContactForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactForm>
 */
class ContactFormFactory extends Factory
{
    /**
     * Define the model's default state: an enabled form without extra fields.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->slug(2);

        return [
            'key' => substr(str_replace('-', '_', $key), 0, 40),
            'title' => ['en' => 'Get in touch', 'pl' => 'Napisz do nas'],
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }
}
