<?php

namespace App\Http\Requests\Admin\Settings;

use App\Models\SiteSetting;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Coming soon and site-wide noindex switches from the System page.
 */
class UpdateSitePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', SiteSetting::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'coming_soon' => ['required', 'boolean'],
            'noindex' => ['required', 'boolean'],
        ];
    }
}
