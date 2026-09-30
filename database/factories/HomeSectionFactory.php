<?php

namespace Database\Factories;

use App\Actions\Home\EnsureHomeSections;
use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeSection>
 */
class HomeSectionFactory extends Factory
{
    /**
     * Define the model's default state: a disabled hero in the default
     * public locale with placeholder content of its type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'en',
            'type' => HomeSectionType::Hero,
            'position' => fn (array $attributes): int => HomeSectionType::from(
                $attributes['type'] instanceof HomeSectionType ? $attributes['type']->value : $attributes['type'],
            )->defaultPosition(),
            'enabled' => false,
            'content' => fn (array $attributes) => EnsureHomeSections::placeholder(
                $attributes['type'] instanceof HomeSectionType ? $attributes['type'] : HomeSectionType::from($attributes['type']),
                $attributes['locale'],
            ),
        ];
    }

    /**
     * A section of the given type (with placeholder content of that type).
     */
    public function ofType(HomeSectionType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => ['enabled' => true]);
    }
}
