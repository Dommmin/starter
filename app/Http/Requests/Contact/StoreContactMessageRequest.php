<?php

namespace App\Http\Requests\Contact;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Public contact form submission. Anyone may submit (rate limited by the
 * `contact` limiter on the route). Besides field validation it carries two
 * spam signals: the `website` honeypot and the time elapsed since the form
 * was rendered, read from the encrypted `form_token`.
 */
class StoreContactMessageRequest extends FormRequest
{
    public const int MESSAGE_MAX_LENGTH = 5000;

    /**
     * Seconds between rendering the form and submitting it; null until the
     * token has been validated.
     */
    private ?int $elapsedSeconds = null;

    /**
     * Public operation: the contact form is available to every visitor.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Name and email end up in mail headers (Reply-To), so control
     * characters, including CR/LF, are rejected.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/^[^\p{C}]+$/u'],
            'email' => ['required', 'string', 'max:254', 'email:rfc', 'regex:/^[^\p{C}\s]+$/u'],
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
            'website' => ['nullable', 'string', 'max:255'],
            'form_token' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('public.contact.fields.name'),
            'email' => __('public.contact.fields.email'),
            'message' => __('public.contact.fields.message'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('public.contact.errors.invalidCharacters'),
            'email.regex' => __('public.contact.errors.invalidCharacters'),
            'form_token.required' => __('public.contact.errors.formExpired'),
        ];
    }

    /**
     * Decrypt the form token; a tampered or expired token asks the visitor
     * to reload the page instead of storing the message.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('form_token')) {
                    return;
                }

                $renderedAt = $this->renderedAt();
                $maxAgeSeconds = (int) config('contact.form_token_max_age_minutes') * 60;

                if ($renderedAt === null) {
                    $validator->errors()->add('form_token', __('public.contact.errors.formExpired'));

                    return;
                }

                $this->elapsedSeconds = now()->getTimestamp() - $renderedAt;

                if ($this->elapsedSeconds > $maxAgeSeconds) {
                    $validator->errors()->add('form_token', __('public.contact.errors.formExpired'));
                }
            },
        ];
    }

    /**
     * Honeypot filled in, or the form submitted faster than a person can.
     * Call only after validation passed.
     */
    public function isSpam(): bool
    {
        if ($this->filled('website')) {
            return true;
        }

        return $this->elapsedSeconds === null
            || $this->elapsedSeconds < (int) config('contact.min_fill_seconds');
    }

    /**
     * Encrypted token rendered with the form (see ContactFormData).
     */
    public static function issueFormToken(?CarbonImmutable $renderedAt = null): string
    {
        return encrypt(($renderedAt ?? now())->getTimestamp());
    }

    private function renderedAt(): ?int
    {
        try {
            $timestamp = decrypt($this->string('form_token')->toString());
        } catch (DecryptException) {
            return null;
        }

        return is_int($timestamp) ? $timestamp : null;
    }
}
