<?php

namespace App\Data\Contact;

use App\Models\ContactForm;
use App\Models\ContactFormField;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Configurable part of the public contact form: its key (sent back with the
 * submission) and the extra fields after name, e-mail and message.
 */
#[TypeScript]
class PublicContactFormData extends Data
{
    /**
     * @param  list<PublicContactFieldData>  $fields
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $fields,
    ) {}

    /**
     * Requires the `fields` relation to be eager loaded.
     */
    public static function fromForm(ContactForm $form, string $locale, string $fallback): self
    {
        return new self(
            key: $form->key,
            title: $form->titleIn($locale, $fallback),
            fields: array_values($form->fields->map(
                fn (ContactFormField $field): PublicContactFieldData => PublicContactFieldData::fromField($field, $locale, $fallback),
            )->all()),
        );
    }
}
