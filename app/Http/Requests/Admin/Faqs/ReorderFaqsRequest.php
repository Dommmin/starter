<?php

namespace App\Http\Requests\Admin\Faqs;

use App\Models\Faq;
use Illuminate\Foundation\Http\FormRequest;

class ReorderFaqsRequest extends FormRequest
{
    /**
     * The route checks `reorder`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', Faq::class) ?? false;
    }

    /**
     * `ids` is the complete new order of every faq; whether it is exactly
     * the current set is checked under lock by the action.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'list', 'min:1'],
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
