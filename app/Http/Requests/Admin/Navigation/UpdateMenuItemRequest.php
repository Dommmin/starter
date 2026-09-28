<?php

namespace App\Http\Requests\Admin\Navigation;

use App\Data\Navigation\MenuItemInputData;
use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The menu (location and locale) of an existing item cannot change.
 */
class UpdateMenuItemRequest extends FormRequest
{
    /**
     * The route checks `update`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        $item = $this->route('menuItem');

        return $item instanceof MenuItem && ($this->user()?->can('update', $item) ?? false);
    }

    /**
     * Blank optional inputs mean "none".
     */
    protected function prepareForValidation(): void
    {
        $this->merge(MenuItemInputRules::blankInputsAsNull($this->all()));
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic locking).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'updated_at' => ['required', 'string', 'date'],
            ...MenuItemInputRules::rules($this),
        ];
    }

    public function itemInput(): MenuItemInputData
    {
        return MenuItemInputRules::input($this);
    }

    public function expectedUpdatedAt(): string
    {
        return $this->string('updated_at')->toString();
    }
}
