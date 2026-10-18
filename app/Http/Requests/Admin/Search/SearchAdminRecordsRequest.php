<?php

namespace App\Http\Requests\Admin\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Term of the command palette search. Panel access is checked by the route
 * middleware; each source is filtered by its own `viewAny` ability.
 */
class SearchAdminRecordsRequest extends FormRequest
{
    public const int MIN_LENGTH = 2;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:100'],
        ];
    }

    public function term(): string
    {
        return trim($this->string('q')->toString());
    }
}
