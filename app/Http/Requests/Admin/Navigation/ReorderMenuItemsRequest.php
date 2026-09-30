<?php

namespace App\Http\Requests\Admin\Navigation;

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New order of the siblings under `parentId` (null = first level) of one
 * menu. `ids` must be exactly the current siblings; otherwise the action
 * answers with a `conflict` error and stores nothing.
 */
class ReorderMenuItemsRequest extends FormRequest
{
    /**
     * The route checks `reorder`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', MenuItem::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'location' => ['required', Rule::enum(MenuLocation::class)],
            'locale' => ['required', 'string', Rule::in(app(LocalizationConfig::class)->getPublicLocales())],
            'parentId' => ['present', 'nullable', 'integer'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct'],
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

    public function parentId(): ?int
    {
        return $this->filled('parentId') ? $this->integer('parentId') : null;
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');

        return array_map(intval(...), $ids);
    }
}
