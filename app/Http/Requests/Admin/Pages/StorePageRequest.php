<?php

namespace App\Http\Requests\Admin\Pages;

use App\Data\Content\PageTranslationInputData;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
{
    /**
     * The route checks `create`; publishing any translation additionally
     * requires the `publish` ability.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', Page::class)) {
            return false;
        }

        return ! $this->translationRules()->requestsPublication($this)
            || $user->can('publish', Page::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'layout_mode' => ['nullable', 'string', Rule::in(['rich_text', 'sections'])],
            ...$this->translationRules()->rules($this),
        ];
    }

    public function layoutMode(): string
    {
        $mode = $this->validated('layout_mode');

        return is_string($mode) ? $mode : 'rich_text';
    }

    /**
     * @return array<string, PageTranslationInputData>
     */
    public function translations(): array
    {
        return $this->translationRules()->toInput($this->validated());
    }

    private function translationRules(): PageTranslationRules
    {
        return app(PageTranslationRules::class);
    }
}
