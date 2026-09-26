<?php

namespace App\Http\Requests\Admin\Faqs;

use App\Models\Faq;
use Illuminate\Foundation\Http\FormRequest;

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
            'position' => ['nullable', 'integer', 'min:-2147483648', 'max:2147483647'],
            'published' => ['required', 'boolean'],
        ];
    }
}
