<?php

namespace App\Http\Requests\Admin\Pages;

use App\Data\Content\PageTranslationInputData;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
{
    /**
     * The route checks `update`; publishing any translation additionally
     * requires the `publish` ability.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $page = $this->route('page');

        if ($user === null || ! $page instanceof Page || ! $user->can('update', $page)) {
            return false;
        }

        return ! $this->translationRules()->requestsPublication($this)
            || $user->can('publish', Page::class);
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic locking).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Page $page */
        $page = $this->route('page');

        return [
            'updated_at' => ['required', 'string', 'date'],
            'layout_mode' => ['nullable', 'string', Rule::in(['rich_text', 'sections'])],
            ...$this->translationRules()->rules($this, $page->id),
        ];
    }

    public function layoutMode(): ?string
    {
        $mode = $this->validated('layout_mode');

        return is_string($mode) ? $mode : null;
    }

    /**
     * @return array<string, PageTranslationInputData>
     */
    public function translations(): array
    {
        return $this->translationRules()->toInput($this->validated());
    }

    public function expectedUpdatedAt(): string
    {
        return $this->string('updated_at')->toString();
    }

    private function translationRules(): PageTranslationRules
    {
        return app(PageTranslationRules::class);
    }
}
