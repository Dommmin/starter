<?php

namespace App\Http\Requests\Admin\Contact;

use App\Enums\LeadStatus;
use App\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactMessageWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ContactMessage|null $message */
        $message = $this->route('contactMessage');

        return $message !== null && ($this->user()?->can('update', $message) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lead_status' => ['required', 'string', Rule::enum(LeadStatus::class)],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function leadStatus(): LeadStatus
    {
        return LeadStatus::from($this->string('lead_status')->toString());
    }

    public function internalNotes(): ?string
    {
        $notes = $this->input('internal_notes');

        return is_string($notes) && trim($notes) !== '' ? trim($notes) : null;
    }
}
