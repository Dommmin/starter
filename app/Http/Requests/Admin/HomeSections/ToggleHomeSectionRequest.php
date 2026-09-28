<?php

namespace App\Http\Requests\Admin\HomeSections;

use App\Models\HomeSection;
use Illuminate\Foundation\Http\FormRequest;

class ToggleHomeSectionRequest extends FormRequest
{
    /**
     * The route checks `update`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        $section = $this->route('homeSection');

        return $section instanceof HomeSection && ($this->user()?->can('update', $section) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
