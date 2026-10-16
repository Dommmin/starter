<?php

namespace App\Data\Admin\ContactForms;

use App\Models\ContactForm;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state of the contact form editor. `updatedAt` is null for a new form
 * and is sent back on update (optimistic locking).
 */
#[TypeScript]
class ContactFormFormData extends Data
{
    /**
     * @param  array<string, string>  $title  Keyed by public locale.
     * @param  list<ContactFormFieldFormData>  $fields
     */
    public function __construct(
        public ?int $id,
        public ?string $updatedAt,
        public string $key,
        public bool $enabled,
        public array $title,
        public array $fields,
    ) {}

    /**
     * @param  list<string>  $locales
     */
    public static function blank(array $locales): self
    {
        return new self(
            id: null,
            updatedAt: null,
            key: '',
            enabled: true,
            title: array_fill_keys($locales, ''),
            fields: [],
        );
    }

    /**
     * Requires the `fields` relation to be loaded.
     *
     * @param  list<string>  $locales
     */
    public static function fromModel(ContactForm $form, array $locales): self
    {
        return new self(
            id: $form->id,
            updatedAt: $form->updated_at?->toIso8601String(),
            key: $form->key,
            enabled: $form->enabled,
            title: array_combine($locales, array_map(fn (string $locale): string => $form->title[$locale] ?? '', $locales)),
            fields: array_values($form->fields->map(fn ($field): ContactFormFieldFormData => ContactFormFieldFormData::fromField($field, $locales))->all()),
        );
    }
}
