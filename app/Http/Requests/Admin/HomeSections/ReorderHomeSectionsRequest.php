<?php

namespace App\Http\Requests\Admin\HomeSections;

use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderHomeSectionsRequest extends FormRequest
{
    /**
     * The route checks `reorder`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', HomeSection::class) ?? false;
    }

    /**
     * `ids` is the complete new order of the locale's sections; whether it
     * is exactly the current set is checked under lock by the action.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(app(LocalizationConfig::class)->getPublicLocales())],
            'ids' => ['required', 'array', 'list', 'min:1', 'max:'.count(HomeSectionType::cases())],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), $this->validated('ids')));
    }
}
