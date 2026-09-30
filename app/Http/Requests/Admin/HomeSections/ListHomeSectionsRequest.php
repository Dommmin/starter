<?php

namespace App\Http\Requests\Admin\HomeSections;

use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListHomeSectionsRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny`); this request
     * only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', Rule::in(app(LocalizationConfig::class)->getPublicLocales())],
        ];
    }

    /**
     * Selected content locale; the default public locale when absent.
     */
    public function locale(): string
    {
        $locale = $this->validated('locale');

        return is_string($locale) ? $locale : app(LocalizationConfig::class)->getPublicDefault();
    }
}
