<?php

namespace Database\Seeders;

use App\Enums\ContactFieldType;
use App\Models\ContactForm;
use Illuminate\Database\Seeder;

/**
 * Default contact forms of every environment: `contact` (name, e-mail and
 * message only) and `quote` (plus budget, deadline and a phone consent).
 * Idempotent: an existing form key is left as the administrator changed it.
 */
class ContactFormSeeder extends Seeder
{
    public function run(): void
    {
        $this->form(ContactForm::DEFAULT_KEY, ['pl' => 'Napisz do nas', 'en' => 'Get in touch', 'de' => 'Schreiben Sie uns'], []);

        $this->form('quote', ['pl' => 'Zapytaj o wycenę', 'en' => 'Request a quote', 'de' => 'Angebot anfragen'], [
            [
                'key' => 'budget',
                'type' => ContactFieldType::Select,
                'required' => true,
                'label' => ['pl' => 'Budżet', 'en' => 'Budget', 'de' => 'Budget'],
                'options' => [
                    ['value' => 'under_5k', 'label' => ['pl' => 'do 5 tys. zł', 'en' => 'under 5k', 'de' => 'unter 5.000']],
                    ['value' => '5k_15k', 'label' => ['pl' => '5–15 tys. zł', 'en' => '5k–15k', 'de' => '5.000–15.000']],
                    ['value' => 'over_15k', 'label' => ['pl' => 'powyżej 15 tys. zł', 'en' => 'over 15k', 'de' => 'über 15.000']],
                ],
            ],
            [
                'key' => 'deadline',
                'type' => ContactFieldType::Text,
                'required' => false,
                'label' => ['pl' => 'Oczekiwany termin', 'en' => 'Expected deadline', 'de' => 'Gewünschter Termin'],
                'options' => null,
            ],
            [
                'key' => 'phone_ok',
                'type' => ContactFieldType::Checkbox,
                'required' => false,
                'label' => ['pl' => 'Możecie do mnie zadzwonić', 'en' => 'You may call me', 'de' => 'Sie dürfen mich anrufen'],
                'options' => null,
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $title
     * @param  list<array{key: string, type: ContactFieldType, required: bool, label: array<string, string>, options: list<array{value: string, label: array<string, string>}>|null}>  $fields
     */
    private function form(string $key, array $title, array $fields): void
    {
        if (ContactForm::query()->where('key', $key)->exists()) {
            return;
        }

        $form = ContactForm::query()->create(['key' => $key, 'title' => $title, 'enabled' => true]);

        foreach ($fields as $position => $field) {
            $form->fields()->create([...$field, 'position' => $position]);
        }
    }
}
