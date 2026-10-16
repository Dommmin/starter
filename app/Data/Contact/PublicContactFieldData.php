<?php

namespace App\Data\Contact;

use App\Enums\ContactFieldType;
use App\Models\ContactFormField;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Extra field of the public contact form in the current locale; submitted
 * as `fields.{key}`.
 */
#[TypeScript]
class PublicContactFieldData extends Data
{
    /**
     * @param  list<PublicContactFieldOptionData>  $options  Select fields only.
     */
    public function __construct(
        public string $key,
        public ContactFieldType $type,
        public string $label,
        public bool $required,
        public array $options,
    ) {}

    public static function fromField(ContactFormField $field, string $locale, string $fallback): self
    {
        return new self(
            key: $field->key,
            type: $field->type,
            label: $field->labelIn($locale, $fallback),
            required: $field->required,
            options: array_map(
                fn (array $option): PublicContactFieldOptionData => new PublicContactFieldOptionData(
                    value: $option['value'],
                    label: $option['label'][$locale] ?? $option['label'][$fallback] ?? $option['value'],
                ),
                $field->options ?? [],
            ),
        );
    }
}
