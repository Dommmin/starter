<?php

namespace App\Data\Admin\ContactForms;

use App\Enums\ContactFieldType;
use App\Models\ContactFormField;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Editable extra field. `options` is the select option list as text, one
 * option per line: `value | label in each public locale` (in the order of
 * `ContactFormEditorData::$locales`).
 */
#[TypeScript]
class ContactFormFieldFormData extends Data
{
    /**
     * @param  array<string, string>  $label  Keyed by public locale.
     */
    public function __construct(
        public string $key,
        public ContactFieldType $type,
        public bool $required,
        public array $label,
        public string $options,
    ) {}

    /**
     * @param  list<string>  $locales
     */
    public static function fromField(ContactFormField $field, array $locales): self
    {
        $lines = [];
        foreach ($field->options ?? [] as $option) {
            $cells = [$option['value']];
            foreach ($locales as $locale) {
                $cells[] = $option['label'][$locale] ?? '';
            }
            $lines[] = rtrim(implode(' | ', $cells), ' |');
        }

        return new self(
            key: $field->key,
            type: $field->type,
            required: $field->required,
            label: array_combine($locales, array_map(fn (string $locale): string => $field->label[$locale] ?? '', $locales)),
            options: implode("\n", $lines),
        );
    }
}
