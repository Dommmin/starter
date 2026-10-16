<?php

namespace App\Http\Requests\Admin\ContactForms;

use App\Enums\ContactFieldType;
use App\Models\ContactForm;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a contact form with its extra fields. Labels and the
 * title are required in the default public locale; select options arrive
 * as text lines `value | label per locale` and are parsed here.
 */
class SaveContactFormRequest extends FormRequest
{
    public const int MAX_FIELDS = 20;

    public const int MAX_OPTIONS = 30;

    private const string KEY_PATTERN = '/^[a-z][a-z0-9_]{1,39}$/';

    private const string CONTROL_CHARACTERS = '/\p{Cc}/u';

    public function authorize(): bool
    {
        $form = $this->route('contactForm');

        return $form instanceof ContactForm
            ? ($this->user()?->can('update', $form) ?? false)
            : ($this->user()?->can('create', ContactForm::class) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $form = $this->route('contactForm');
        $default = $this->localization()->getPublicDefault();
        $singleLine = 'not_regex:'.self::CONTROL_CHARACTERS;

        $rules = [
            'updated_at' => ['nullable', 'string', 'date'],
            'key' => ['required', 'string', 'regex:'.self::KEY_PATTERN, Rule::unique('contact_forms', 'key')->ignore($form instanceof ContactForm ? $form->id : null)],
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'array:'.implode(',', $this->locales())],
            'fields' => ['present', 'array', 'list', 'max:'.self::MAX_FIELDS],
            'fields.*' => ['required', 'array:key,type,required,label,options'],
            'fields.*.key' => ['required', 'string', 'regex:'.self::KEY_PATTERN, 'distinct'],
            'fields.*.type' => ['required', Rule::enum(ContactFieldType::class)],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.label' => ['required', 'array:'.implode(',', $this->locales())],
            'fields.*.options' => ['nullable', 'string', 'max:6000', 'required_if:fields.*.type,'.ContactFieldType::Select->value],
        ];

        foreach ($this->locales() as $locale) {
            $presence = $locale === $default ? 'required' : 'nullable';
            $rules["title.{$locale}"] = [$presence, 'string', 'max:120', $singleLine];
            $rules["fields.*.label.{$locale}"] = [$presence, 'string', 'max:120', $singleLine];
        }

        return $rules;
    }

    /**
     * Each option line must start with a unique value key.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('fields', []) as $index => $field) {
                    if (! is_array($field) || ($field['type'] ?? null) !== ContactFieldType::Select->value) {
                        continue;
                    }

                    $options = self::parseOptions((string) ($field['options'] ?? ''), $this->locales());
                    $values = array_column($options, 'value');

                    if ($options === [] || count($options) > self::MAX_OPTIONS) {
                        $validator->errors()->add("fields.{$index}.options", __('admin.contactForms.errors.optionsCount', ['max' => self::MAX_OPTIONS]));
                    } elseif (count($values) !== count(array_unique($values)) || preg_grep(self::KEY_PATTERN, $values, PREG_GREP_INVERT) !== []) {
                        $validator->errors()->add("fields.{$index}.options", __('admin.contactForms.errors.optionsFormat'));
                    }
                }
            },
        ];
    }

    public function expectedUpdatedAt(): ?string
    {
        $updatedAt = $this->validated('updated_at');

        return is_string($updatedAt) ? $updatedAt : null;
    }

    /**
     * Normalized attributes of the form and its fields in order.
     *
     * @return array{key: string, enabled: bool, title: array<string, string>, fields: list<array{key: string, type: ContactFieldType, required: bool, label: array<string, string>, options: list<array{value: string, label: array<string, string>}>|null}>}
     */
    public function formValues(): array
    {
        $validated = $this->validated();
        $fields = [];

        foreach ($validated['fields'] as $field) {
            $type = ContactFieldType::from($field['type']);
            $fields[] = [
                'key' => $field['key'],
                'type' => $type,
                'required' => (bool) $field['required'],
                'label' => self::texts($field['label']),
                'options' => $type === ContactFieldType::Select ? self::parseOptions((string) $field['options'], $this->locales()) : null,
            ];
        }

        return [
            'key' => $validated['key'],
            'enabled' => (bool) $validated['enabled'],
            'title' => self::texts($validated['title']),
            'fields' => $fields,
        ];
    }

    /**
     * Option lines `value | label in each locale`; blank lines are skipped
     * and missing labels fall back to the value at display time.
     *
     * @param  list<string>  $locales
     * @return list<array{value: string, label: array<string, string>}>
     */
    public static function parseOptions(string $text, array $locales): array
    {
        $options = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $cells = array_map('trim', explode('|', $line));

            if ($cells[0] === '') {
                continue;
            }

            $labels = [];
            foreach ($locales as $position => $locale) {
                $label = mb_substr($cells[$position + 1] ?? '', 0, 120);

                if ($label !== '') {
                    $labels[$locale] = $label;
                }
            }

            $options[] = ['value' => $cells[0], 'label' => $labels];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function texts(array $values): array
    {
        return array_filter(array_map(fn (mixed $value): string => trim((string) $value), $values), fn (string $value): bool => $value !== '');
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return $this->localization()->getPublicLocales();
    }

    private function localization(): LocalizationConfig
    {
        return app(LocalizationConfig::class);
    }
}
