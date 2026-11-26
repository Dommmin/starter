<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\SiteSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AI provider and API key from the System page. The key is required when
 * none is stored or the provider changes; otherwise an empty key keeps the
 * stored one.
 */
class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', SiteSetting::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'api_key' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->keyIsRequired()),
                'string',
                'min:8',
                'max:512',
                'regex:/^\S+$/',
            ],
        ];
    }

    public function provider(): AiProvider
    {
        return $this->enum('provider', AiProvider::class) ?? AiProvider::Anthropic;
    }

    public function apiKey(): ?string
    {
        $key = $this->string('api_key')->trim()->toString();

        return $key === '' ? null : $key;
    }

    /**
     * A key must be given when none is stored or the provider changes.
     */
    private function keyIsRequired(): bool
    {
        $setting = AiSetting::query()->find(AiSetting::SINGLETON_ID);

        return $setting === null
            || ! $setting->isConfigured()
            || $setting->provider?->value !== $this->input('provider');
    }
}
