<?php

namespace Database\Factories;

use App\Enums\ContactFieldType;
use App\Models\ContactForm;
use App\Models\ContactFormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactFormField>
 */
class ContactFormFieldFactory extends Factory
{
    /**
     * Define the model's default state: an optional text field.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_form_id' => ContactForm::factory(),
            'key' => 'field_'.fake()->unique()->numberBetween(1, 99999),
            'type' => ContactFieldType::Text,
            'required' => false,
            'position' => 0,
            'label' => ['en' => 'Company', 'pl' => 'Firma'],
            'options' => null,
        ];
    }

    public function required(): static
    {
        return $this->state(fn (): array => ['required' => true]);
    }

    /**
     * A select field with the given option values (labels repeat the value).
     *
     * @param  list<string>  $values
     */
    public function select(array $values = ['small', 'large']): static
    {
        return $this->state(fn (): array => [
            'type' => ContactFieldType::Select,
            'options' => array_map(fn (string $value): array => ['value' => $value, 'label' => ['en' => ucfirst($value)]], $values),
        ]);
    }

    public function checkbox(): static
    {
        return $this->state(fn (): array => ['type' => ContactFieldType::Checkbox]);
    }
}
