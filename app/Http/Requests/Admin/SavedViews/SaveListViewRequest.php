<?php

namespace App\Http\Requests\Admin\SavedViews;

use App\Support\Listing\SavedListViews;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Save the current parameters of an admin list under a name. The list is
 * a route name `admin.*.index`; visiting the saved URL authorizes again.
 */
class SaveListViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'list' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! SavedListViews::isList($value)) {
                    $fail(__('admin.savedViews.errors.list'));
                }
            }],
            'name' => ['required', 'string', 'max:60', 'not_regex:/\p{Cc}/u'],
            'query' => ['present', 'array', 'max:20'],
            'query.*' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function savedQuery(): array
    {
        /** @var array<string, string|null> $query */
        $query = $this->validated('query');

        return SavedListViews::cleanQuery($query);
    }
}
