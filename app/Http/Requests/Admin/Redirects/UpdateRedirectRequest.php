<?php

namespace App\Http\Requests\Admin\Redirects;

use App\Models\Redirect;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRedirectRequest extends FormRequest
{
    /**
     * The route checks `update`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        $redirect = $this->route('redirect');

        return $redirect instanceof Redirect && ($this->user()?->can('update', $redirect) ?? false);
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic locking).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $redirect = $this->route('redirect');

        return [
            'updated_at' => ['required', 'string', 'date'],
            ...StoreRedirectRequest::fieldRules($redirect instanceof Redirect ? $redirect->id : null),
        ];
    }

    /**
     * Validated field values without the version, ready to be stored.
     *
     * @return array{source_path: string, target: string, status_code: int, is_active: bool}
     */
    public function fieldValues(): array
    {
        /** @var array{source_path: string, target: string, status_code: int, is_active: bool} */
        return $this->safe()->only(['source_path', 'target', 'status_code', 'is_active']);
    }

    public function expectedUpdatedAt(): string
    {
        return $this->string('updated_at')->toString();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(StoreRedirectRequest::normalizedInput($this->all()));
    }
}
