<?php

namespace App\Http\Requests\Admin\Redirects;

use App\Models\Redirect;
use Illuminate\Foundation\Http\FormRequest;

class DestroyRedirectsRequest extends FormRequest
{
    /**
     * Selection is limited to one list page; `ListQuery` caps a page at 100.
     */
    public const int MAX_IDS = 100;

    /**
     * The route checks `viewAny`; the controller authorizes `delete` for
     * every selected redirect, so one forbidden record rejects the batch.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Redirect::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), $this->validated('ids')));
    }
}
