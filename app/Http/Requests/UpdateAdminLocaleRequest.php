<?php

namespace App\Http\Requests;

use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(LocalizationConfig $config): array
    {
        return [
            'locale' => ['required', 'string', Rule::in($config->getAdminLocales())],
        ];
    }
}
