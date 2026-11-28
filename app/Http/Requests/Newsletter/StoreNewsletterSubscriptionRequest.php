<?php

namespace App\Http\Requests\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsletterSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `website` is the honeypot: people never see the field.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'newsletter_email' => ['required', 'string', 'email:rfc', 'max:254'],
            'newsletter_consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:200'],
        ];
    }
}
