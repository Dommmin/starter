<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\MaintenanceDuration;
use App\Models\SiteSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Maintenance switch and expected duration from the System page.
 */
class UpdateSiteMaintenanceRequest extends FormRequest
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
            'maintenance' => ['required', 'boolean'],
            'duration' => ['required', Rule::enum(MaintenanceDuration::class)],
        ];
    }

    public function duration(): MaintenanceDuration
    {
        return $this->enum('duration', MaintenanceDuration::class) ?? MaintenanceDuration::Unknown;
    }
}
