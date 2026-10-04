<?php

namespace App\Http\Requests\Admin\Contact;

use App\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;

class DestroyContactMessagesRequest extends FormRequest
{
    /**
     * Selection is limited to one list page; `ListQuery` caps a page at 100.
     */
    public const int MAX_IDS = 100;

    /**
     * The route checks `viewAny`; every selected message is authorized for
     * `delete` by the action, so one forbidden message rejects the batch.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ContactMessage::class) ?? false;
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
