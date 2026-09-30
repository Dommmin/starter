<?php

namespace App\Http\Requests\Admin\Faqs;

use App\Models\Faq;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFaqRequest extends FormRequest
{
    /**
     * The route checks `create`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Faq::class) ?? false;
    }

    /**
     * Blank optional number and date inputs mean "no value".
     */
    protected function prepareForValidation(): void
    {
        $this->merge(self::blankOptionalInputsAsNull($this->all()));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Validation of the editable fields, shared with UpdateFaqRequest.
     *
     * @return array<string, list<mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:20000'],
            'locale' => ['nullable', 'string', Rule::in(app(LocalizationConfig::class)->getPublicLocales())],
            'position' => ['nullable', 'integer', 'min:-2147483648', 'max:2147483647'],
            'published' => ['required', 'boolean'],
        ];
    }

    /**
     * Null for each optional number/date/locale input sent as a blank string
     * (a blank locale means "every language").
     *
     * @param  array<string, mixed>  $input
     * @return array<string, null>
     */
    public static function blankOptionalInputsAsNull(array $input): array
    {
        $blank = [];
        foreach (['position', 'locale'] as $name) {
            if (is_string($input[$name] ?? null) && trim($input[$name]) === '') {
                $blank[$name] = null;
            }
        }

        return $blank;
    }
}
