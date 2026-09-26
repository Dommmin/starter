<?php

namespace App\Http\Requests\Admin\Faqs;

use App\Models\Faq;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFaqRequest extends FormRequest
{
    /**
     * The route checks `update`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        $faq = $this->route('faq');

        return $faq instanceof Faq && ($this->user()?->can('update', $faq) ?? false);
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic locking).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'updated_at' => ['required', 'string', 'date'],
            ...StoreFaqRequest::fieldRules(),
        ];
    }

    /**
     * Validated field values without the version.
     *
     * @return array<string, mixed>
     */
    public function fieldValues(): array
    {
        return $this->safe()->except(['updated_at']);
    }

    public function expectedUpdatedAt(): string
    {
        return $this->string('updated_at')->toString();
    }
}
