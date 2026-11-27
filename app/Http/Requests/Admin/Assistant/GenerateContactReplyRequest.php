<?php

namespace App\Http\Requests\Admin\Assistant;

use App\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;

class GenerateContactReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ContactMessage::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contact_message_id' => ['required', 'integer', 'exists:contact_messages,id'],
            'guidance' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
