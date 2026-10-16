<?php

namespace App\Data\Admin\ContactForms;

use App\Models\ContactForm;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row of the contact form list. Requires `fields_count`.
 */
#[TypeScript]
class ContactFormListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $key,
        public string $title,
        public bool $enabled,
        public int $fieldsCount,
    ) {}

    public static function fromModel(ContactForm $form, string $locale, string $fallback): self
    {
        return new self(
            id: $form->id,
            key: $form->key,
            title: $form->titleIn($locale, $fallback),
            enabled: $form->enabled,
            fieldsCount: (int) $form->getAttribute('fields_count'),
        );
    }
}
