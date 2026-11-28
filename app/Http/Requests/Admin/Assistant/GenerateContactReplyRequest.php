<?php

namespace App\Http\Requests\Admin\Assistant;

use App\Enums\AssistantFeature;
use App\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Message to draft a reply for. 404 while the feature is unavailable, so no
 * sender data reaches the AI provider; only users who may read messages ask.
 */
class GenerateContactReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(AssistantFeature::ReplyDraft->isAvailable(), 404);

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
