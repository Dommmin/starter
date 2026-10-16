<?php

namespace App\Http\Requests\Contact;

use App\Enums\ContactFieldType;
use App\Models\ContactForm;
use App\Models\ContactFormField;
use App\Repositories\Contact\ContactFormRepository;
use App\Services\Localization\LocalizationConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Public contact form submission. Anyone may submit (rate limited by the
 * `contact` limiter on the route). Besides field validation it carries two
 * spam signals: the `website` honeypot and the time elapsed since the form
 * was rendered, read from the encrypted `form_token`.
 *
 * `form` names the configured form (default `contact`); its extra fields
 * arrive as `fields.{key}` and are validated from the configuration only:
 * keys outside it are rejected, select values must be one of the options.
 */
class StoreContactMessageRequest extends FormRequest
{
    public const int MESSAGE_MAX_LENGTH = 5000;

    /**
     * Seconds between rendering the form and submitting it; null until the
     * token has been validated.
     */
    private ?int $elapsedSeconds = null;

    /** Resolved form; false until resolved. */
    private ContactForm|false|null $resolvedForm = false;

    /**
     * Public operation: the contact form is available to every visitor.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Name and email end up in mail headers (Reply-To), so control
     * characters, including CR/LF, are rejected.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $form = $this->contactForm();
        $fields = $form === null ? [] : $form->fields->all();

        $rules = [
            'name' => ['required', 'string', 'max:120', 'regex:/^[^\p{C}]+$/u'],
            'email' => ['required', 'string', 'max:254', 'email:rfc', 'regex:/^[^\p{C}\s]+$/u'],
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
            'website' => ['nullable', 'string', 'max:255'],
            'form_token' => ['required', 'string'],
            'form' => ['nullable', 'string', 'max:40'],
            'fields' => $fields === []
                ? ['prohibited']
                : ['nullable', 'array:'.implode(',', array_map(fn (ContactFormField $field): string => $field->key, $fields))],
        ];

        foreach ($fields as $field) {
            $rules["fields.{$field->key}"] = self::fieldRules($field);
        }

        return $rules;
    }

    /**
     * The configured form of this submission: `form` or the default form;
     * null when it does not exist or is disabled.
     */
    public function contactForm(): ?ContactForm
    {
        if ($this->resolvedForm === false) {
            $key = $this->input('form');
            $this->resolvedForm = app(ContactFormRepository::class)->enabled(is_string($key) && $key !== '' ? $key : ContactForm::DEFAULT_KEY);
        }

        return $this->resolvedForm;
    }

    /**
     * Validated extra values keyed by field key: trimmed text, the chosen
     * option, true/false for checkboxes; empty optional fields are left out.
     *
     * @return array<string, string|bool>
     */
    public function extraFields(): array
    {
        $form = $this->contactForm();
        /** @var array<string, mixed> $submitted */
        $submitted = (array) $this->validated('fields', []);
        $values = [];

        foreach ($form === null ? [] : $form->fields as $field) {
            $value = $submitted[$field->key] ?? null;

            if ($field->type === ContactFieldType::Checkbox) {
                $values[$field->key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $values[$field->key] = trim($value);
            }
        }

        return $values;
    }

    /**
     * @return list<mixed>
     */
    private static function fieldRules(ContactFormField $field): array
    {
        $presence = $field->required ? 'required' : 'nullable';

        return match ($field->type) {
            ContactFieldType::Text => [$presence, 'string', 'max:'.$field->type->maxLength(), 'regex:/^[^\p{C}]*$/u'],
            ContactFieldType::Textarea => [$presence, 'string', 'max:'.$field->type->maxLength()],
            ContactFieldType::Select => [$presence, 'string', Rule::in($field->optionValues())],
            ContactFieldType::Checkbox => $field->required ? ['accepted'] : ['nullable', 'boolean'],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'name' => __('public.contact.fields.name'),
            'email' => __('public.contact.fields.email'),
            'message' => __('public.contact.fields.message'),
        ];

        $form = $this->contactForm();

        foreach ($form === null ? [] : $form->fields as $field) {
            $attributes["fields.{$field->key}"] = $field->labelIn(app()->getLocale(), app(LocalizationConfig::class)->getPublicFallback());
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('public.contact.errors.invalidCharacters'),
            'email.regex' => __('public.contact.errors.invalidCharacters'),
            'form_token.required' => __('public.contact.errors.formExpired'),
        ];
    }

    /**
     * Decrypt the form token; a tampered or expired token asks the visitor
     * to reload the page instead of storing the message.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $key = $this->input('form');
                if (is_string($key) && $key !== '' && $this->contactForm() === null) {
                    $validator->errors()->add('form', __('public.contact.errors.formExpired'));
                }

                if ($validator->errors()->has('form_token')) {
                    return;
                }

                $renderedAt = $this->renderedAt();
                $maxAgeSeconds = (int) config('contact.form_token_max_age_minutes') * 60;

                if ($renderedAt === null) {
                    $validator->errors()->add('form_token', __('public.contact.errors.formExpired'));

                    return;
                }

                $this->elapsedSeconds = now()->getTimestamp() - $renderedAt;

                if ($this->elapsedSeconds > $maxAgeSeconds) {
                    $validator->errors()->add('form_token', __('public.contact.errors.formExpired'));
                }
            },
        ];
    }

    /**
     * Honeypot filled in, or the form submitted faster than a person can.
     * Call only after validation passed.
     */
    public function isSpam(): bool
    {
        if ($this->filled('website')) {
            return true;
        }

        return $this->elapsedSeconds === null
            || $this->elapsedSeconds < (int) config('contact.min_fill_seconds');
    }

    /**
     * Encrypted token rendered with the form (see ContactFormData).
     */
    public static function issueFormToken(?CarbonImmutable $renderedAt = null): string
    {
        return encrypt(($renderedAt ?? now())->getTimestamp());
    }

    private function renderedAt(): ?int
    {
        try {
            $timestamp = decrypt($this->string('form_token')->toString());
        } catch (DecryptException) {
            return null;
        }

        return is_int($timestamp) ? $timestamp : null;
    }
}
