<?php

namespace App\Http\Requests\Admin;

use App\Actions\Content\CreateContentPreviewLink;
use App\Models\Article;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentPreviewLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $this->user()?->can('update', $record) ?? false;
    }

    /**
     * The language must have a saved translation, otherwise the link would be empty.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in($this->record()->translations()->pluck('locale')->all())],
            'days' => ['required', 'integer', Rule::in(CreateContentPreviewLink::LIFETIMES)],
        ];
    }

    public function record(): Page|Article
    {
        $record = $this->route('page') ?? $this->route('article');

        abort_unless($record instanceof Page || $record instanceof Article, 404);

        return $record;
    }
}
