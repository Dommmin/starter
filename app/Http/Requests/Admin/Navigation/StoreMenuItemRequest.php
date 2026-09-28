<?php

namespace App\Http\Requests\Admin\Navigation;

use App\Data\Navigation\MenuItemInputData;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    /**
     * The route checks `create`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', MenuItem::class) ?? false;
    }

    /**
     * Blank optional inputs mean "none".
     */
    protected function prepareForValidation(): void
    {
        $this->merge(MenuItemInputRules::blankInputsAsNull($this->all()));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'location' => ['required', Rule::enum(MenuLocation::class)],
            'locale' => ['required', 'string', Rule::in(app(LocalizationConfig::class)->getPublicLocales())],
            ...MenuItemInputRules::rules($this),
        ];
    }

    public function location(): MenuLocation
    {
        return MenuLocation::from($this->string('location')->toString());
    }

    public function menuLocale(): string
    {
        return $this->string('locale')->toString();
    }

    public function itemInput(): MenuItemInputData
    {
        return MenuItemInputRules::input($this);
    }
}
